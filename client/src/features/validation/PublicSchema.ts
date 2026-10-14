import { hasExactKeys } from '@/api/decodeResponse';

export type PublicRule = Readonly<{
  type: 'Required' | 'Type' | 'MinLength' | 'MaxLength' | 'Same';
  args: string;
  message: string;
  dependsOn: readonly string[];
}>;
export type PublicField = Readonly<{ clientField: string; rules: readonly PublicRule[] }>;
export type PublicSchema = Readonly<{
  revision: string;
  formName: string;
  fields: Readonly<Record<string, PublicField>>;
}>;

const wireName = /^[a-z][a-z0-9_]{0,63}$/;
const clientName = /^[a-z][a-zA-Z0-9]{0,63}$/;
const formName = /^[a-z][a-z0-9_]{0,63}$/;
const revision = /^[a-f0-9]{64}$/;
const safeMessage = (value: unknown): value is string =>
  typeof value === 'string' &&
  value.length > 0 &&
  value.length <= 256 &&
  ![...value].some(
    (char) => char.charCodeAt(0) < 32 || char.charCodeAt(0) === 127 || '<>'.includes(char)
  );

/**
 * Decodes the exact published safe subset, never an executable rule or unbounded server object
 */
export function decodePublicSchema(source: unknown): PublicSchema | null {
  if (!hasExactKeys(source, ['schema_version', 'revision', 'form_name', 'fields'])) return null;
  if (
    source.schema_version !== 1 ||
    typeof source.revision !== 'string' ||
    !revision.test(source.revision) ||
    typeof source.form_name !== 'string' ||
    !formName.test(source.form_name) ||
    typeof source.fields !== 'object' ||
    source.fields === null ||
    Array.isArray(source.fields)
  )
    return null;
  const entries = Object.entries(source.fields as Record<string, unknown>);
  if (entries.length === 0 || entries.length > 64) return null;
  const fields: Record<string, PublicField> = {};
  const clients = new Set<string>();
  for (const [wire, field] of entries) {
    if (!wireName.test(wire) || !hasExactKeys(field, ['client_field', 'rules'])) return null;
    if (
      typeof field.client_field !== 'string' ||
      !clientName.test(field.client_field) ||
      clients.has(field.client_field) ||
      !Array.isArray(field.rules) ||
      field.rules.length === 0 ||
      field.rules.length > 16
    )
      return null;
    clients.add(field.client_field);
    const rules: PublicRule[] = [];
    for (const rule of field.rules as unknown[]) {
      if (!hasExactKeys(rule, ['type', 'args', 'message', 'depends_on'])) return null;
      if (
        !['Required', 'Type', 'MinLength', 'MaxLength', 'Same'].includes(String(rule.type)) ||
        typeof rule.args !== 'string' ||
        !safeMessage(rule.message) ||
        !Array.isArray(rule.depends_on) ||
        !rule.depends_on.every((item: unknown) => typeof item === 'string' && clientName.test(item))
      )
        return null;
      if (
        (rule.type === 'Required' && (rule.args !== '' || rule.depends_on.length !== 0)) ||
        (rule.type === 'Type' && (rule.args !== 'string' || rule.depends_on.length !== 0)) ||
        ((rule.type === 'MinLength' || rule.type === 'MaxLength') &&
          (!/^(0|[1-9][0-9]{0,3})$/.test(rule.args) ||
            Number(rule.args) > 9999 ||
            rule.depends_on.length !== 0)) ||
        (rule.type === 'Same' && (!wireName.test(rule.args) || rule.depends_on.length !== 1))
      )
        return null;
      rules.push(
        Object.freeze({
          type: rule.type as PublicRule['type'],
          args: rule.args,
          message: rule.message,
          dependsOn: Object.freeze([...(rule.depends_on as string[])])
        })
      );
    }
    fields[wire] = Object.freeze({ clientField: field.client_field, rules: Object.freeze(rules) });
  }
  for (const field of Object.values(fields)) {
    for (const rule of field.rules) {
      if (rule.type === 'Same' && fields[rule.args]?.clientField !== rule.dependsOn[0]) return null;
    }
  }
  return Object.freeze({
    revision: source.revision,
    formName: source.form_name,
    fields: Object.freeze(fields)
  });
}

/**
 * Evaluates only the published PHP-compatible subset against JSON primitive wire values
 *
 * Required checks presence, not non-emptiness; server-only password and credential policy stays server-side
 */
export function validatePublicSchema(
  schema: PublicSchema,
  values: Readonly<Record<string, unknown>>
): Readonly<Record<string, readonly string[]>> {
  const errors: Record<string, readonly string[]> = {};
  for (const field of Object.values(schema.fields)) {
    const present = Object.hasOwn(values, field.clientField);
    const value = values[field.clientField];
    const messages: string[] = [];
    for (const rule of field.rules) {
      let valid = true;
      if (rule.type === 'Required') valid = present;
      else if (!present) continue;
      else if (rule.type === 'Type') valid = typeof value === 'string';
      else if (rule.type === 'Same') {
        const peer = schema.fields[rule.args]?.clientField;
        valid = peer !== undefined && (!Object.hasOwn(values, peer) || value === values[peer]);
      } else {
        const text =
          typeof value === 'string'
            ? value
            : value === null
              ? ''
              : typeof value === 'boolean'
                ? value
                  ? '1'
                  : ''
                : typeof value === 'number' && Number.isFinite(value)
                  ? String(value)
                  : null;
        valid =
          text !== null &&
          (rule.type === 'MinLength'
            ? [...text].length >= Number(rule.args)
            : [...text].length <= Number(rule.args));
      }
      if (!valid && !messages.includes(rule.message)) messages.push(rule.message);
    }
    if (messages.length > 0) errors[field.clientField] = Object.freeze(messages);
  }
  return Object.freeze(errors);
}
