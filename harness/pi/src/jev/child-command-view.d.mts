import type { ChildCommandRequest } from './child-command-approval.mjs';
export class ChildCommandView {
  constructor(request: ChildCommandRequest, options: {
    size: () => { columns: number; rows: number }; redraw: () => void;
    done: (accepted: boolean) => void; key: (data: string, key: string) => boolean;
  });
  invalidate(): void;
  render(width: number): string[];
  handleInput(data: string): void;
  finish(accepted: boolean): void;
}
