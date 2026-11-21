<?php

declare(strict_types=1);

namespace Tooling\Planning;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Class PlanningPortfolio
 *
 * Validates authoritative Markdown records and refreshes their generated views.
 *
 * @phpstan-type Fields array<string, string>
 * @phpstan-type Records array<string, array{string, Fields}>
 * @phpstan-type Decisions array<string, list<array{string, string, string, Fields, list<string>}>>
 * @phpstan-type Projection array{string, string, string}
 */
final class PlanningPortfolio
{
    private const KINDS = ['epics' => 'EPIC', 'tickets' => 'TICKET', 'tasks' => 'TASK'];
    private const STATUSES = [
        'needs-triage', 'needs-info', 'ready-for-agent', 'ready-for-human', 'in-progress', 'done', 'wontfix'
    ];
    private const TERMINAL = ['done', 'wontfix'];
    private const HOLDS = [
        'owner-decision'            => 'Owner decision needed',
        'supplier-contract'         => 'Supplier contract/release needed',
        'engineering-qualification' => 'Engineering qualification needed'
    ];
    private const LINK = '~!?\[[^\]]*\]\(([^\s)]+)\)~u';
    private const MAP = '~^\*\*Label:\*\*[^\r\n]*wayfinder:map~m';
    private const BLOCK = '~<!-- planning:([a-z-]+) -->\r?\n.*?<!-- /planning:\1 -->~s';

    private readonly string $planning;

    /**
     * Constructs PlanningPortfolio
     *
     * @param string $root Absolute checkout path
     */
    public function __construct(private readonly string $root)
    {
        $this->planning = $root.'/planning';
    }

    /**
     * Validates records and optionally applies the planned refresh
     *
     * @param boolean $write Allow refresh writes
     *
     * @return integer Command exit code
     */
    public function run(bool $write): int
    {
        [$records, $errors] = $this->loadRecords();
        $this->validateReferences($records, $errors);
        $decisions = $this->loadDecisions($errors);
        foreach ($this->markdown($this->planning) as $path) {
            if (str_starts_with(basename($path), '_')) {
                continue;
            }
            foreach (self::links($this->read($path)) as $target) {
                $destination = explode('#', $target, 2)[0];
                if (
                    str_ends_with($destination, '.md') && !preg_match('~^(?:/|https?:|mailto:)~', $destination)
                    && !is_file(dirname($path).'/'.$destination)
                ) {
                    $errors[] = $this->relative($path).": broken local Markdown link $target";
                }
            }
        }
        $git = proc_open(
            ['git', '-c', 'safe.directory='.$this->root, 'check-ignore', '-q', '.runs/planning-check'],
            [STDIN, STDOUT, STDERR],
            $pipes,
            $this->root
        );
        if ($git === false || proc_close($git) !== 0) {
            $errors[] = '.runs/ must be gitignored';
        }

        if ($errors === []) {
            $closures = $this->completeParents($records);
            $pending = $write ? $closures : [];
            if (!$write) {
                foreach (array_keys($closures) as $path) {
                    $name = $this->relative($path);
                    $errors[] = "$name: parent completion out of sync; run ./bin/planning-check --write";
                }
            }
            foreach ($this->projections($records, $decisions) as [$path, $name, $content]) {
                if (!is_file($path)) {
                    $errors[] = 'missing view: '.$this->relative($path);
                    continue;
                }
                $text = $pending[$path] ?? $this->read($path);
                preg_match_all(self::BLOCK, $text, $blocks, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
                $matches = array_values(array_filter(
                    $blocks,
                    static fn (array $match): bool => $match[1][0] === $name
                ));
                if (count($matches) !== 1) {
                    $errors[] = $this->relative($path).": requires one planning:$name block";
                    continue;
                }
                [$current, $offset] = $matches[0][0];
                $expected = "<!-- planning:$name -->\n$content\n<!-- /planning:$name -->";
                if (str_replace("\r\n", "\n", $current) !== $expected) {
                    if ($write) {
                        if (str_contains($current, "\r\n")) {
                            $expected = str_replace("\n", "\r\n", $expected);
                        }
                        $pending[$path] = substr_replace($text, $expected, $offset, strlen($current));
                    } else {
                        $errors[] = $this->relative($path).": stale $name view; run ./bin/planning-check --write";
                    }
                }
            }
            if ($errors === []) {
                foreach ($pending as $path => $text) {
                    if (file_put_contents($path, $text) !== strlen($text)) {
                        throw new RuntimeException('Cannot write '.$this->relative($path));
                    }
                }
                foreach (array_keys($closures) as $path) {
                    echo 'Closed parent: '.$this->relative($path)."\n";
                }
                ksort($pending, SORT_STRING);
                foreach (array_keys($pending) as $path) {
                    echo 'Updated: '.$this->relative($path)."\n";
                }
            }
        }
        if ($errors !== []) {
            echo "Planning validation failed:\n- ".implode("\n- ", $errors)."\n";

            return 1;
        }
        $active = count(array_filter($records, static fn (array $record): bool => !self::terminal($record[1])));
        $mode = $write ? 'refreshed' : 'current';
        echo 'Planning validation passed: '.count($records)." records, $active active; generated views $mode\n";

        return 0;
    }

    /**
     * Strips surrounding whitespace from metadata
     *
     * @param string $text Field or key
     *
     * @return string Trimmed text
     */
    private static function trim(string $text): string
    {
        return preg_replace('/^\s+|\s+$/u', '', $text);
    }

    /**
     * Checks a terminal delivery outcome
     *
     * @param array $data Delivery record fields
     *
     * @phpstan-param Fields $data
     *
     * @return boolean Whether the status is terminal
     */
    private static function terminal(array $data): bool
    {
        return in_array($data['status'], self::TERMINAL, true);
    }

    /**
     * Lists comma-separated blocker IDs
     *
     * @param array $data Delivery record fields
     *
     * @phpstan-param Fields $data
     *
     * @return list<string>
     */
    private static function blockers(array $data): array
    {
        return array_values(array_filter(
            array_map(self::trim(...), explode(',', $data['blocked_by'] ?? '')),
            static fn (string $id): bool => $id !== ''
        ));
    }

    /**
     * Returns the parent ID for an index row
     *
     * @param array $data Delivery record fields
     *
     * @phpstan-param Fields $data
     *
     * @return string Parent ID or empty string
     */
    private static function parent(array $data): string
    {
        return ($data['ticket'] ?? '') !== '' ? $data['ticket'] : ($data['epic'] ?? '');
    }

    /**
     * Returns children while preserving order
     *
     * @param array  $records Delivery records
     * @param string $key     Parent field name
     * @param string $id      Parent ID
     *
     * @phpstan-param Records $records
     *
     * @return Records
     */
    private static function children(array $records, string $key, string $id): array
    {
        return array_filter($records, static fn (array $child): bool => ($child[1][$key] ?? '') === $id);
    }

    /**
     * Reads a Wayfinder field from its Markdown label
     *
     * @param string $text  Markdown contents
     * @param string $field Field label
     *
     * @return string Field value or empty string
     */
    private static function metadata(string $text, string $field): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        preg_match('/^\*\*'.preg_quote($field, '/').':\*\*\s*(.*?)$/mu', $text, $match);

        return self::trim($match[1] ?? '');
    }

    /**
     * Lists targets from Markdown inline links and images
     *
     * @param string $text Markdown contents
     *
     * @return list<string>
     */
    private static function links(string $text): array
    {
        preg_match_all(self::LINK, $text, $matches);

        return $matches[1];
    }

    /**
     * Resolves a Wayfinder link to its actual path
     *
     * @param string $source Linking document path
     * @param string $target Link target
     *
     * @return string|false Resolved path or failure
     */
    private static function resolve(string $source, string $target): string|false
    {
        return realpath(str_starts_with($target, '/') ? $target : dirname($source).'/'.$target);
    }

    /**
     * Renders a link relative to the generated view
     *
     * @param string $source Generated view path
     * @param string $target Destination path
     * @param string $label  Link label
     *
     * @return string Markdown link
     */
    private static function link(string $source, string $target, string $label): string
    {
        $from = explode('/', dirname($source));
        $to = explode('/', $target);
        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }
        $path = str_repeat('../', count($from)).implode('/', $to);

        return '['.self::cell($label)."]($path)";
    }

    /**
     * Formats text for a table cell
     *
     * @param string $value Raw cell value
     *
     * @return string Rendered cell value
     */
    private static function cell(string $value): string
    {
        return $value === '' ? '—' : str_replace(['|', "\n"], ['&#124;', ' '], $value);
    }

    /**
     * Renders a Markdown table with an explicit empty state
     *
     * @param array $headers Column headings
     * @param array $rows    Rendered cell values
     *
     * @phpstan-param list<string> $headers
     * @phpstan-param list<list<string>> $rows
     *
     * @return string Markdown table
     */
    private static function table(array $headers, array $rows): string
    {
        if ($rows === []) {
            $rows[] = ['None', ...array_fill(0, count($headers) - 1, '—')];
        }
        $lines = ['| '.implode(' | ', $headers).' |', '|'.str_repeat('---|', count($headers))];
        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', $row).' |';
        }

        return implode("\n", $lines);
    }

    /**
     * Parses and validate delivery records
     *
     * @return array{Records,list<string>}
     */
    private function loadRecords(): array
    {
        $records = $errors = [];
        foreach (self::KINDS as $directory => $kind) {
            foreach ($this->markdown($this->planning.'/'.$directory) as $path) {
                $filename = basename($path);
                if (str_starts_with($filename, '_') || in_array($filename, ['README.md', 'BOARD.md'], true)) {
                    continue;
                }
                try {
                    $data = $this->frontmatter($path);
                    $id = $data['id'] ?? '';
                    if (!preg_match('/^'.$kind.'-[0-9]{5}$/D', $id)) {
                        throw new InvalidArgumentException("invalid $kind identifier: $id");
                    }
                    if ($filename !== substr($id, strlen($kind) + 1)."-$kind.md") {
                        throw new InvalidArgumentException('filename and identifier disagree');
                    }
                    if (isset($records[$id])) {
                        throw new InvalidArgumentException("duplicate identifier: $id");
                    }
                    if (!in_array($data['status'] ?? '', self::STATUSES, true) || ($data['title'] ?? '') === '') {
                        throw new InvalidArgumentException('missing title or invalid status');
                    }
                    if (($data['order'] ?? '') !== '' && !preg_match('/^0*[1-9][0-9]*$/D', $data['order'])) {
                        throw new InvalidArgumentException('order must be a positive integer');
                    }
                    if (($data['pr'] ?? '') !== '' && !preg_match('~^https://[^\s|)]+/pull/[0-9]+$~Du', $data['pr'])) {
                        throw new InvalidArgumentException('pr must be a pull-request URL');
                    }
                    $hold = $data['hold_category'] ?? '';
                    if ($hold !== '' && !isset(self::HOLDS[$hold])) {
                        throw new InvalidArgumentException('invalid hold_category');
                    }
                    if (
                        $kind === 'TASK' && $data['status'] === 'needs-info'
                        && ($hold === '' || ($data['hold_reason'] ?? '') === '')
                    ) {
                        throw new InvalidArgumentException('needs-info TASK requires hold_category and hold_reason');
                    }
                    if ($this->archived($path) && !self::terminal($data)) {
                        throw new InvalidArgumentException('archived record is not terminal');
                    }
                    $records[$id] = [$path, $data];
                } catch (InvalidArgumentException $exception) {
                    $errors[] = $this->relative($path).': '.$exception->getMessage();
                }
            }
        }

        return [$records, $errors];
    }

    /**
     * Validates parents and blockers and detect cycles
     *
     * @param array $records Delivery records
     * @param array $errors  Collected diagnostics
     *
     * @phpstan-param Records $records
     * @phpstan-param list<string> $errors
     */
    private function validateReferences(array $records, array &$errors): void
    {
        foreach ($records as $id => [, $data]) {
            $task = str_starts_with($id, 'TASK-');
            $parentKey = $task ? 'ticket' : 'epic';
            $parent = $data[$parentKey] ?? '';
            $parentKind = $task ? 'TICKET-' : 'EPIC-';
            if ($parent !== '' && (!isset($records[$parent]) || !str_starts_with($parent, $parentKind))) {
                $errors[] = "$id: invalid $parentKey parent $parent";
            }
            if ($task && $parent === '' && !in_array($data['kind'] ?? '', ['chore', 'bug'], true)) {
                $errors[] = "$id: requires a TICKET parent or explicit standalone chore/bug kind";
            }
            if (str_starts_with($id, 'TICKET-') && $parent === '') {
                $errors[] = "$id: requires an EPIC parent";
            }
            if (isset($data['prd'])) {
                $errors[] = "$id: legacy prd field must be ticket";
            }
            foreach (self::blockers($data) as $blocker) {
                if (!isset($records[$blocker]) || !str_starts_with($blocker, 'TASK-')) {
                    $errors[] = "$id: invalid TASK blocker $blocker";
                }
            }
        }
        $visiting = $visited = [];
        $visit = static function (string $id) use (&$visit, &$visiting, &$visited, $records, &$errors): void {
            if (isset($visiting[$id])) {
                $errors[] = "dependency cycle at $id";

                return;
            }
            if (isset($visited[$id]) || !isset($records[$id])) {
                return;
            }
            $visiting[$id] = true;
            foreach (self::blockers($records[$id][1]) as $blocker) {
                $visit($blocker);
            }
            unset($visiting[$id]);
            $visited[$id] = true;
        };
        foreach (array_keys($records) as $id) {
            $visit($id);
        }
    }

    /**
     * Validates and group Wayfinder decisions by map
     *
     * @param array $errors Collected diagnostics
     *
     * @phpstan-param list<string> $errors
     *
     * @return Decisions
     */
    private function loadDecisions(array &$errors): array
    {
        $paths = array_values(array_filter(
            $this->markdown($this->planning.'/wayfinder/tickets'),
            static fn (string $path): bool => str_starts_with(basename($path), 'WF-')
        ));
        $known = [];
        foreach ($paths as $path) {
            $resolved = realpath($path);
            if ($resolved !== false) {
                $known[$resolved] = true;
            }
        }
        $seen = $decisions = [];
        foreach ($paths as $path) {
            $id = substr(basename($path), 0, 6);
            $source = $this->relative($path);
            if (isset($seen[$id])) {
                $errors[] = "duplicate Wayfinder identifier: $id";
            }
            $seen[$id] = true;
            $text = $this->read($path);
            $data = [];
            foreach (['Map', 'Status', 'Mode', 'Labels', 'Depends on'] as $field) {
                $data[$field] = self::metadata($text, $field);
                if ($data[$field] === '') {
                    $errors[] = "$source: missing $field";
                }
            }
            $owner = self::links($data['Map'])[0] ?? '';
            $map = $owner === '' ? false : self::resolve($path, $owner);
            if ($map === false || !is_file($map) || !preg_match(self::MAP, $this->read($map))) {
                $errors[] = "$source: requires an owning Wayfinder map";
            }
            $dependencies = [];
            foreach (self::links($data['Depends on']) as $target) {
                $dependency = self::resolve($path, $target);
                if ($dependency === false || !isset($known[$dependency])) {
                    $errors[] = "$source: invalid Wayfinder dependency $target";
                } else {
                    $dependencies[] = $dependency;
                }
            }
            $title = ltrim(explode("\n", str_replace(["\r\n", "\r"], "\n", $text), 2)[0], '# ');
            $decisions[$map ?: ''][] = [$path, $id, $title, $data, $dependencies];
        }

        return $decisions;
    }

    /**
     * Builds bottom-up parent closure from live and archived records
     *
     * @param array $records Delivery records for the new views
     *
     * @phpstan-param Records $records
     *
     * @return array<string,string> Pending contents keyed by path
     */
    private function completeParents(array &$records): array
    {
        $pending = [];
        $ids = array_keys($records);
        sort($ids, SORT_STRING);
        foreach (['TICKET-' => 'ticket', 'EPIC-' => 'epic'] as $prefix => $key) {
            foreach ($ids as $id) {
                [$path, $data] = $records[$id];
                if (!str_starts_with($id, $prefix) || $this->archived($path) || self::terminal($data)) {
                    continue;
                }
                $children = self::children($records, $key, $id);
                $unfinished = array_any($children, static fn (array $child): bool => !self::terminal($child[1]));
                if ($children === [] || $unfinished) {
                    continue;
                }
                $completed = array_any($children, static fn (array $child): bool => $child[1]['status'] === 'done');
                $status = $completed ? 'done' : 'wontfix';
                $text = $this->read($path);
                if (preg_match('~\A---\r?\n(.*?)\r?\n---\r?\n~s', $text, $header, PREG_OFFSET_CAPTURE) !== 1) {
                    throw new RuntimeException('Cannot update frontmatter: '.$this->relative($path));
                }
                [$block, $offset] = $header[1];
                $updated = preg_replace('/^(\h*status\h*:)[^\r\n]*/m', '${1} '.$status, $block, 1);
                $pending[$path] = substr_replace($text, $updated, $offset, strlen($block));
                $records[$id] = [$path, [...$data, 'status' => $status]];
            }
        }

        return $pending;
    }

    /**
     * Builds each named view in check order
     *
     * @param array $records   Delivery records
     * @param array $decisions Wayfinder decisions
     *
     * @phpstan-param Records $records
     * @phpstan-param Decisions $decisions
     *
     * @return iterable<Projection>
     */
    private function projections(array $records, array $decisions): iterable
    {
        yield [$this->planning.'/tasks/BOARD.md', 'board', $this->board($records)];
        $ordered = $records;
        ksort($ordered, SORT_STRING);
        foreach (self::KINDS as $directory => $kind) {
            foreach ([false, true] as $archived) {
                $source = $this->planning."/$directory/".($archived ? 'archive/README.md' : 'README.md');
                $rows = [];
                foreach ($ordered as $id => [$path, $data]) {
                    if (!str_starts_with($id, "$kind-") || $this->archived($path) !== $archived) {
                        continue;
                    }
                    $parent = self::parent($data);
                    $rows[] = [self::link($source, $path, $id), self::cell($data['title']), $data['status'],
                        $parent === '' ? '—' : self::link($source, $records[$parent][0], $parent)];
                }
                yield [$source, 'records', self::table(['ID', 'Title', 'Status', 'Parent'], $rows)];
            }
        }
        yield from $this->roadmap($ordered);
        foreach ($records as $id => [$path]) {
            if ($this->archived($path) || str_starts_with($id, 'TASK-')) {
                continue;
            }
            $rows = [];
            $key = str_starts_with($id, 'EPIC-') ? 'epic' : 'ticket';
            foreach (self::children($ordered, $key, $id) as $child => [$target, $data]) {
                $rows[] = [self::link($path, $target, $child), self::cell($data['title']), $data['status']];
            }
            yield [$path, 'children', self::table(['ID', 'Title', 'Status'], $rows)];
        }
        yield from $this->wayfinder($decisions);
    }

    /**
     * Builds Board sections from ordered live tasks
     *
     * @param array $records Delivery records
     *
     * @phpstan-param Records $records
     *
     * @return string Board Markdown
     */
    private function board(array $records): string
    {
        $board = $this->planning.'/tasks/BOARD.md';
        $tasks = array_filter(
            $records,
            fn (array $record, string $id): bool => str_starts_with($id, 'TASK-') && !$this->archived($record[0]),
            ARRAY_FILTER_USE_BOTH
        );
        // Compare numeric strings without integer overflow or loss of precision.
        $order = static function (array $data): string {
            $value = $data['order'] ?? '';

            return $value === '' ? '999999' : ltrim($value, '0');
        };
        uksort($tasks, static function (string $left, string $right) use ($tasks, $order): int {
            $a = $order($tasks[$left][1]);
            $b = $order($tasks[$right][1]);

            return (strlen($a) <=> strlen($b)) ?: strcmp($a, $b) ?: strcmp($left, $right);
        });
        $groups = array_fill_keys([
            'Active Work', 'Ready Frontier', 'Waiting', ...array_values(self::HOLDS),
            'Human Action', 'Needs Triage', 'Recently Closed'
        ], []);
        foreach ($tasks as $id => [$path, $data]) {
            $blockers = [];
            foreach (self::blockers($data) as $blocker) {
                if (!self::terminal($records[$blocker][1])) {
                    $blockers[] = self::link($board, $records[$blocker][0], $blocker);
                }
            }
            $status = $data['status'];
            $group = match ($status) {
                'in-progress' => 'Active Work',
                'ready-for-human' => 'Human Action',
                'needs-triage' => 'Needs Triage',
                'needs-info' => self::HOLDS[$data['hold_category']],
                'done', 'wontfix' => 'Recently Closed',
                default => $blockers === [] ? 'Ready Frontier' : 'Waiting',
            };
            $parent = $data['ticket'] ?? '';
            $parentLabel = '— (standalone '.($data['kind'] ?? 'task').')';
            if ($parent !== '') {
                [$parentPath, $parentData] = $records[$parent];
                $parentLabel = self::link($board, $parentPath, $parent.' — '.$parentData['title']);
            }
            $pr = $data['pr'] ?? '';
            $groups[$group][] = [self::cell($data['order'] ?? ''), self::link($board, $path, $id),
                self::cell($data['title']), $parentLabel, $status, $blockers === [] ? '—' : implode(', ', $blockers),
                ...($status === 'needs-info' ? [self::cell($data['hold_reason'])] : []),
                $pr === '' ? '—' : '[PR #'.basename($pr)."]($pr)"];
        }
        $sections = [];
        foreach ($groups as $name => $rows) {
            $headers = ['Order', 'TASK ID', 'Title', 'Parent TICKET', 'Status', 'Blocked by'];
            if (in_array($name, self::HOLDS, true)) {
                $headers[] = 'Needed input or evidence';
            }
            $sections[] = "## $name\n\n".self::table([...$headers, 'PR'], $rows);
        }

        return implode("\n\n", $sections);
    }

    /**
     * Projects the roadmap inventory and frontier
     *
     * @param array $records Delivery records sorted by ID
     *
     * @phpstan-param Records $records
     *
     * @return iterable<Projection>
     */
    private function roadmap(array $records): iterable
    {
        $roadmap = $this->planning.'/ROADMAP.md';
        $epics = $epicsWithoutTickets = $ticketsWithoutTasks = [];
        foreach ($records as $id => [$path, $data]) {
            if ($this->archived($path) || str_starts_with($id, 'TASK-')) {
                continue;
            }
            $link = self::link($roadmap, $path, $id);
            $title = self::cell($data['title']);
            $status = $data['status'];
            if (str_starts_with($id, 'EPIC-')) {
                $epics[] = [$link, $title, self::cell($data['target'] ?? ''), $status];
                if (!self::terminal($data) && self::children($records, 'epic', $id) === []) {
                    $epicsWithoutTickets[] = [$link, $title, $status];
                }
            } elseif (!self::terminal($data) && self::children($records, 'ticket', $id) === []) {
                $parent = $data['epic'];
                $ticketsWithoutTasks[] = [$link, $title, self::link($roadmap, $records[$parent][0], $parent), $status];
            }
        }
        yield [$roadmap, 'epics', self::table(['EPIC ID', 'Title', 'Target', 'Status'], $epics)];
        $frontier = [
            '### EPICs without TICKETs',
            self::table(['EPIC ID', 'Title', 'Status'], $epicsWithoutTickets),
            '### TICKETs without TASKs',
            self::table(['TICKET ID', 'Title', 'Parent EPIC', 'Status'], $ticketsWithoutTasks)
        ];
        yield [$roadmap, 'frontier', implode("\n\n", $frontier)];
    }

    /**
     * Projects Wayfinder indexes and decision tables
     *
     * @param array $decisions Wayfinder decisions
     *
     * @phpstan-param Decisions $decisions
     *
     * @return iterable<Projection>
     */
    private function wayfinder(array $decisions): iterable
    {
        $directory = $this->planning.'/wayfinder';
        $maps = [[], []];
        foreach ($this->markdown($directory) as $path) {
            if (str_starts_with(basename($path), '_')) {
                continue;
            }
            $text = $this->read($path);
            if (!preg_match(self::MAP, $text)) {
                continue;
            }
            $rows = [];
            $mapDecisions = $decisions[realpath($path)] ?? [];
            foreach ($mapDecisions as [$decision, $id, $title, $data, $dependencies]) {
                $links = array_map(
                    static fn (string $other): string => self::link($path, $other, substr(basename($other), 0, 6)),
                    $dependencies
                );
                $rows[] = [
                    self::link($path, $decision, $id), self::cell($title),
                    self::cell(str_replace('`', '', $data['Labels'])),
                    self::cell($data['Mode']), self::cell($data['Status']),
                    $links === [] ? '—' : implode(', ', $links)
                ];
            }
            $headers = ['Decision ID', 'Title', 'Type', 'Mode', 'Status', 'Depends on'];
            yield [$path, 'decisions', self::table($headers, $rows)];
            $maps[(int) $this->archived($path)][] = [$path, self::metadata($text, 'Status')];
        }
        foreach ([$directory.'/README.md', $directory.'/archive/maps/README.md'] as $index => $source) {
            $rows = [];
            foreach ($maps[$index] as [$path, $status]) {
                $rows[] = [self::link($source, $path, basename($path, '.md')), self::cell($status)];
            }
            yield [$source, 'maps', self::table(['Map', 'Status'], $rows)];
        }
    }

    /**
     * Finds and sort Markdown paths without following directory symlinks
     *
     * @param string $directory Directory to scan
     *
     * @return list<string>
     */
    private function markdown(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $paths = [];
        $entries = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
        foreach (new RecursiveIteratorIterator($entries) as $file) {
            if (str_ends_with($file->getFilename(), '.md')) {
                $paths[] = $file->getPathname();
            }
        }
        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * Reads UTF-8 Markdown without changing its bytes
     *
     * @param string $path Markdown path
     *
     * @return string Exact contents
     */
    private function read(string $path): string
    {
        $text = file_get_contents($path);
        if ($text === false || !preg_match('//u', $text)) {
            throw new RuntimeException('Cannot read UTF-8 Markdown: '.$this->relative($path));
        }

        return $text;
    }

    /**
     * Parses flat Planning metadata
     *
     * @param string $path Record path
     *
     * @return Fields
     */
    private function frontmatter(string $path): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $this->read($path));
        if (!preg_match('~\A---\n(.*?)\n---\n~s', $text, $match)) {
            throw new InvalidArgumentException('missing frontmatter');
        }
        $data = [];
        foreach (explode("\n", $match[1]) as $line) {
            $parts = explode(':', $line, 2);
            $key = self::trim($parts[0]);
            if (count($parts) !== 2 || isset($data[$key])) {
                throw new InvalidArgumentException("invalid or duplicate frontmatter field: $line");
            }
            $data[$key] = self::trim($parts[1]);
        }

        return $data;
    }

    /**
     * Returns a path relative to the checkout when possible
     *
     * @param string $path Whole path
     *
     * @return string Path for diagnostics
     */
    private function relative(string $path): string
    {
        return str_starts_with($path, $this->root.'/') ? substr($path, strlen($this->root) + 1) : $path;
    }

    /**
     * Checks an archive folder by path segment
     *
     * @param string $path Whole path
     *
     * @return boolean Whether the path is archived
     */
    private function archived(string $path): bool
    {
        return in_array('archive', explode('/', $this->relative($path)), true);
    }
}
