import { describe, expect, it } from 'vitest';

import { decodePublicSchema, validatePublicSchema } from '@/features/validation/PublicSchema';

import { fixture } from './fixtures';

const schema = decodePublicSchema(fixture)!;

describe('public validation metadata and PHP-compatible rules', () => {
  it('consumes the real PHP array-argument wire shape instead of a synthetic string shape', () => {
    const actualWire = {
      ...fixture,
      fields: {
        display_name: {
          client_field: 'displayName',
          rules: [
            { type: 'Required', args: [], message: 'Provide a value.', depends_on: [] },
            { type: 'Type', args: ['string'], message: 'Use text.', depends_on: [] },
            { type: 'MinLength', args: ['2'], message: 'Use two characters.', depends_on: [] }
          ]
        }
      }
    };
    const decoded = decodePublicSchema(actualWire);
    expect(decoded).not.toBeNull();
    expect(validatePublicSchema(decoded!, { displayName: 'x' })).toEqual({
      displayName: ['Use two characters.']
    });
  });
  it.each(['', [''], ['string', 'extra'], [4]])(
    'rejects invalid argument arity/types rather than coercing them (%#)',
    (args) => {
      expect(
        decodePublicSchema({
          ...fixture,
          fields: {
            display_name: {
              client_field: 'displayName',
              rules: [{ type: 'Required', args, message: 'Required.', depends_on: [] }]
            }
          }
        })
      ).toBeNull();
    }
  );

  it('accepts a mapped, bounded schema with declared comparison dependencies', () => {
    expect(schema.formName).toBe('example_form');
    expect(schema.fields.confirmation?.rules[0]?.dependsOn).toEqual(['displayName']);
    expect(Object.isFrozen(schema.fields.display_name?.rules)).toBe(true);
  });

  it.each([
    { ...fixture, schema_version: 2 },
    { ...fixture, revision: 'bad' },
    { ...fixture, revision: `${'a'.repeat(64)}\n` },
    { ...fixture, form_name: 'example_form\n' },
    { ...fixture, extra: 'not public' },
    { ...fixture, fields: {} },
    {
      ...fixture,
      fields: {
        display_name: {
          client_field: 'displayName',
          rules: [{ type: 'Eval', args: [], message: 'bad', depends_on: [] }]
        }
      }
    },
    {
      ...fixture,
      fields: {
        display_name: {
          client_field: 'displayName',
          rules: [{ type: 'Required', args: [], message: '<private>', depends_on: [] }]
        }
      }
    },
    { ...fixture, fields: { confirmation: fixture.fields.confirmation } },
    {
      ...fixture,
      fields: {
        display_name: fixture.fields.display_name,
        confirmation: { client_field: 'displayName', rules: fixture.fields.confirmation.rules }
      }
    }
  ])(
    'rejects malformed/unsupported metadata rather than treating it as empty validation %#',
    (input) => {
      expect(decodePublicSchema(input)).toBeNull();
    }
  );

  it('distinguishes absence from an empty value, checks PHP string type and Unicode codepoints', () => {
    expect(validatePublicSchema(schema, { confirmation: '' })).toEqual({
      displayName: ['Provide a value.']
    });
    expect(validatePublicSchema(schema, { displayName: '', confirmation: '' })).toEqual({
      displayName: ['Use two characters.']
    });
    expect(validatePublicSchema(schema, { displayName: 12, confirmation: 12 })).toEqual({
      displayName: ['Use text.']
    });
    expect(validatePublicSchema(schema, { displayName: '😀', confirmation: '😀' })).toEqual({
      displayName: ['Use two characters.']
    });
    expect(validatePublicSchema(schema, { displayName: '😀a', confirmation: '😀a' })).toEqual({});
    expect(validatePublicSchema(schema, { displayName: 'AB', confirmation: 'ab' })).toEqual({
      confirmation: ['Values must match.']
    });
  });
});
