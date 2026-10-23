import { act, fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { ActivationCredential } from '@/features/auth/ActivationCredential';
import { ShellApplication } from '@/ShellApplication';

import { activationSchemaSource, jsonResponse } from '../../activationFixtures';

/** The browser can retain a document on history traversal; retirement must clear its mounted form. */
describe('activation document lifecycle', () => {
  it.each(['pagehide', 'fight:activation-retired'])(
    'removes volatile password fields synchronously on %s',
    async (event) => {
      vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(activationSchemaSource));
      const credential = new ActivationCredential({
        userId: crypto.randomUUID(),
        credential: 'a'.repeat(64)
      });
      render(
        <ShellApplication
          configuration={JSON.stringify({
            schema_version: 2,
            api_base_path: '/api/v1',
            app_debug: false,
            swagger_available: false
          })}
          pathname="/app/activate"
          activation={credential}
        />
      );
      const password = await screen.findByLabelText('New password (required)');
      fireEvent.change(password, { target: { value: 'ephemeral-password-marker' } });
      act(() => {
        window.dispatchEvent(new Event(event));
      });
      expect(credential.current()).toBeNull();
      expect(screen.queryByLabelText('New password (required)')).not.toBeInTheDocument();
      expect(document.documentElement.outerHTML.includes('ephemeral-password-marker')).toBe(false);
      act(() => {
        window.dispatchEvent(new Event('pageshow'));
      });
      expect(screen.queryByLabelText('New password (required)')).not.toBeInTheDocument();
    }
  );
});
