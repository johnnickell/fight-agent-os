declare module '*.scss';
declare module '@fight/react/styles.css';

declare module 'swagger-ui-dist/swagger-ui-bundle.js' {
  import type { SwaggerSystem } from '@/features/swagger/SwaggerSystem';
  const SwaggerUI: (configuration: Readonly<Record<string, unknown>>) => SwaggerSystem;
  export default SwaggerUI;
}
