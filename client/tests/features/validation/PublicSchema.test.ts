import { describe, expect, it } from 'vitest';

import { decodePublicSchema, validatePublicSchema } from '@/features/validation/PublicSchema';

import { fixture } from './fixtures';

const schema = decodePublicSchema(fixture)!;

describe('public validation metadata and PHP-compatible rules', () => {
  it('accepts a mapped, bounded schema with declared comparison dependencies', () => {
    expect(schema.formName).toBe('example_form');
    expect(schema.fields.confirmation?.rules[0]?.dependsOn).toEqual(['displayName']);
    expect(Object.isFrozen(schema.fields.display_name?.rules)).toBe(true);
  });

  it.each([
    { ...fixture, schema_version: 2 },
    { ...fixture, revision: 'bad' },
    { ...fixture, extra: 'not public' },
    { ...fixture, fields: {} },
    {
      ...fixture,
      fields: {
        display_name: {
          client_field: 'displayName',
          rules: [{ type: 'Eval', args: '', message: 'bad', depends_on: [] }]
        }
      }
    },
    {
      ...fixture,
      fields: {
        display_name: {
          client_field: 'displayName',
          rules: [{ type: 'Required', args: '', message: '<private>', depends_on: [] }]
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
