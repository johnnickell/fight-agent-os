import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  broadcastPasswordReset,
  browserSessionPlatform,
  decodeSessionMessage,
  sessionChannelName
} from '@fight/react/account';

afterEach(() => vi.unstubAllGlobals());

describe('browser session messaging', () => {
  it('ignores even available cross-tab APIs on an insecure origin', () => {
    const constructor = vi.fn();
    vi.stubGlobal('isSecureContext', false);
    vi.stubGlobal('BroadcastChannel', constructor);

    expect(browserSessionPlatform()).toEqual({});
    expect(constructor).not.toHaveBeenCalled();
  });

  it('keeps available locking when the secure origin denies the message channel', () => {
    const locks = { request: vi.fn() };
    vi.stubGlobal('isSecureContext', true);
    vi.stubGlobal('navigator', { locks });
    vi.stubGlobal(
      'BroadcastChannel',
      class {
        constructor() {
          throw new DOMException('Channel denied', 'SecurityError');
        }
      }
    );

    expect(browserSessionPlatform()).toEqual({ locks });
  });

  it('sends only the targeted transient reset notice and closes the sender', () => {
    const postMessage = vi.fn();
    const close = vi.fn();
    const opened = vi.fn();
    vi.stubGlobal('isSecureContext', true);
    vi.stubGlobal(
      'BroadcastChannel',
      class {
        postMessage = postMessage;
        close = close;
        constructor(name: string) {
          opened(name);
        }
      }
    );
    const userId = crypto.randomUUID();

    broadcastPasswordReset(userId);

    expect(opened).toHaveBeenCalledWith(sessionChannelName);
    expect(postMessage).toHaveBeenCalledExactlyOnceWith({
      version: 1,
      sender: expect.stringMatching(/^[a-f0-9]{32}$/),
      sequence: 1,
      kind: 'password-reset',
      userId
    });
    expect(decodeSessionMessage(postMessage.mock.calls[0]?.[0])).toMatchObject({ userId });
    expect(close).toHaveBeenCalledOnce();
  });

  it('does not turn a committed reset into an error if posting and cleanup both fail', () => {
    const close = vi.fn(() => {
      throw new DOMException('Channel denied', 'SecurityError');
    });
    vi.stubGlobal('isSecureContext', true);
    vi.stubGlobal(
      'BroadcastChannel',
      class {
        postMessage() {
          throw new DOMException('Channel closed', 'InvalidStateError');
        }
        close = close;
      }
    );

    expect(() => broadcastPasswordReset(crypto.randomUUID())).not.toThrow();
    expect(close).toHaveBeenCalledOnce();
  });
});
