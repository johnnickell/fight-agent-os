export const fixture = {
  schema_version: 1,
  revision: 'a'.repeat(64),
  form_name: 'example_form',
  fields: {
    display_name: {
      client_field: 'displayName',
      rules: [
        { type: 'Required', args: '', message: 'Provide a value.', depends_on: [] },
        { type: 'Type', args: 'string', message: 'Use text.', depends_on: [] },
        { type: 'MinLength', args: '2', message: 'Use two characters.', depends_on: [] }
      ]
    },
    confirmation: {
      client_field: 'confirmation',
      rules: [
        {
          type: 'Same',
          args: 'display_name',
          message: 'Values must match.',
          depends_on: ['displayName']
        }
      ]
    }
  }
};
