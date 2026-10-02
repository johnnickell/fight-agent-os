import { useEffect, useRef, useState } from 'react';

import { useFormik } from 'formik';

import { type PublicSchema, validatePublicSchema } from '@/features/validation/PublicSchema';

import type { ApiResult } from '@/api/ApiResult';
import type { ChangeEvent, RefCallback } from 'react';

export type FieldMessages = Readonly<Record<string, readonly string[]>>;

/**
 * Binds Formik's field and submit seams to revisioned public client/server feedback
 *
 * Only registered fields and published messages may be presented from a server rejection
 */
export function usePublicForm<T>(
  options: Readonly<{
    schema: PublicSchema | null;
    initialValues: Readonly<Record<string, string>>;
    submit: (values: Readonly<Record<string, string>>) => Promise<ApiResult<T>>;
    onSuccess: (result: T) => void;
  }>
) {
  const { schema, initialValues, submit, onSuccess } = options;
  const [errors, setErrors] = useState<FieldMessages>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [submitted, setSubmitted] = useState(false);
  const [busy, setBusy] = useState(false);
  const [touched, setTouched] = useState<Readonly<Record<string, boolean>>>({});
  const values = useRef<Readonly<Record<string, string>>>(initialValues);
  const generations = useRef<Record<string, number>>({});
  const epoch = useRef(0);
  const request = useRef(0);
  const mounted = useRef(true);
  const currentSchema = useRef(schema);
  const elements = useRef<Record<string, HTMLInputElement | null>>({});

  // All mutations run through this adapter: no caller receives the raw Formik setter.
  const formik = useFormik({
    initialValues: { ...initialValues },
    validateOnChange: false,
    validateOnBlur: false,
    onSubmit: async (submittedValues) => {
      const selected = currentSchema.current;
      setSubmitted(true);
      if (!selected) {
        setFormError('Validation is unavailable. Retry loading the form before continuing.');
        return;
      }
      const local = validatePublicSchema(selected, submittedValues);
      if (Object.keys(local).length) {
        setErrors(local);
        focusFirst(local, selected);
        return;
      }
      const id = ++request.current;
      const startEpoch = epoch.current;
      const snapshot = { ...generations.current };
      setBusy(true);
      setFormError(null);
      let result: ApiResult<T>;
      try {
        result = await submit(submittedValues);
      } catch {
        if (mounted.current && id === request.current) {
          setFormError('Unable to complete the request. Please try again.');
          setBusy(false);
        }
        return;
      }
      // An observed server success is never cancelled by a local edit or an ignored error response.
      if (result.ok) {
        onSuccess(result.value);
        if (mounted.current && id === request.current) {
          if (
            Object.keys(generations.current).every(
              (key) => (snapshot[key] ?? 0) === (generations.current[key] ?? 0)
            )
          )
            reset();
          else setBusy(false);
        }
        return;
      }
      if (!mounted.current || id !== request.current || startEpoch !== epoch.current) return;
      setBusy(false);
      if (result.error.kind === 'cancelled') return;
      if (result.error.kind !== 'validation' || !result.error.fields) {
        setFormError('Unable to complete the request. Please check the form and try again.');
        return;
      }
      const mapped: Record<string, readonly string[]> = {
        ...validatePublicSchema(selected, values.current)
      };
      let uncertain = false;
      for (const [path, messages] of Object.entries(result.error.fields)) {
        const wire = /^body\.([a-z][a-z0-9_]*)$/.exec(path)?.[1];
        const field = wire ? selected.fields[wire] : undefined;
        if (!field) {
          uncertain = true;
          continue;
        }
        const approved = field.rules.filter((rule) => messages.includes(rule.message));
        if (approved.length !== messages.length) {
          uncertain = true;
          continue;
        }
        const related = [field.clientField, ...approved.flatMap((rule) => rule.dependsOn)];
        if (related.some((name) => (snapshot[name] ?? 0) !== (generations.current[name] ?? 0)))
          continue;
        mapped[field.clientField] = [
          ...new Set([...(mapped[field.clientField] ?? []), ...messages])
        ];
      }
      // An unclassified cross-field error has no reliable dependency provenance.
      const edited = Object.keys(generations.current).some(
        (key) => (snapshot[key] ?? 0) !== (generations.current[key] ?? 0)
      );
      if (uncertain && edited) return;
      setErrors(mapped);
      if (uncertain || Object.keys(mapped).length === 0) {
        setFormError('Please check the form and try again.');
      }
      if (Object.keys(mapped).length && !edited) focusFirst(mapped, selected);
    }
  });

  function focusFirst(messages: FieldMessages, selected: PublicSchema) {
    for (const field of Object.values(selected.fields)) {
      if (messages[field.clientField]?.length && elements.current[field.clientField]) {
        elements.current[field.clientField]?.focus();
        return;
      }
    }
  }

  function setFieldValue(name: string, next: string) {
    if (!schema || !Object.values(schema.fields).some((field) => field.clientField === name))
      return;
    if (values.current[name] === next) return;
    values.current = { ...values.current, [name]: next };
    generations.current[name] = (generations.current[name] ?? 0) + 1;
    const affected = new Set([name]);
    for (const field of Object.values(schema.fields)) {
      if (field.rules.some((rule) => rule.dependsOn.includes(name))) {
        affected.add(field.clientField);
        generations.current[field.clientField] = (generations.current[field.clientField] ?? 0) + 1;
      }
    }
    setErrors((prior) => {
      const local = validatePublicSchema(schema, values.current);
      const updated = { ...prior };
      for (const field of affected) {
        delete updated[field];
        if ((submitted || touched[field]) && local[field]) updated[field] = local[field];
      }
      return updated;
    });
    setFormError(null);
    void formik.setFieldValue(name, next, false);
  }

  function reset() {
    epoch.current++;
    request.current++;
    generations.current = {};
    values.current = initialValues;
    setErrors({});
    setFormError(null);
    setTouched({});
    setSubmitted(false);
    setBusy(false);
    formik.resetForm({ values: { ...initialValues } });
  }

  // Replacing the published schema invalidates every outstanding result, even if its fields look identical.
  useEffect(() => {
    if (currentSchema.current !== schema) {
      currentSchema.current = schema;
      reset();
    }
  });
  useEffect(() => {
    mounted.current = true;
    const epochRef = epoch;
    const requestRef = request;
    return () => {
      mounted.current = false;
      epochRef.current++;
      requestRef.current++;
    };
  }, []);

  function field(name: string) {
    if (!schema || !Object.values(schema.fields).some((item) => item.clientField === name)) {
      throw new Error('Unregistered form field');
    }
    const ref: RefCallback<HTMLInputElement> = (element) => {
      elements.current[name] = element;
    };
    return {
      name,
      value: formik.values[name] ?? '',
      errors: errors[name] ?? [],
      ref,
      onChange: (event: ChangeEvent<HTMLInputElement>) => setFieldValue(name, event.target.value),
      onBlur: () => {
        setTouched((previous) => ({ ...previous, [name]: true }));
        if (schema) {
          const local = validatePublicSchema(schema, values.current)[name];
          if (local) setErrors((previous) => ({ ...previous, [name]: local }));
        }
      }
    };
  }

  return {
    field,
    setFieldValue,
    reset,
    errors,
    formError,
    busy,
    submitted,
    handleSubmit: formik.handleSubmit
  };
}
