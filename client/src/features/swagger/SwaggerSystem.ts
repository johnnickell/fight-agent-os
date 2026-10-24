/** Narrow qualified surface of the pinned distribution, never an authority source. */
export type SwaggerRequest = Readonly<{ url: string; method?: string; body?: unknown }>;
export type SwaggerResponse = Readonly<{
  url: string;
  status: number;
  statusText: string;
  headers: Readonly<Record<string, string>>;
  text: string;
  data: unknown;
}>;
type Props = Readonly<Record<string, unknown>>;
export type SwaggerSystem = {
  React: {
    Component: new (props: Props) => {
      props: Props;
      state: { retired: boolean };
      setState(state: { retired: boolean }, callback: () => void): void;
    };
    createElement(component: unknown, props: object | null, ...children: unknown[]): unknown;
  };
  Im: { Map(): unknown };
  getComponent(name: string, container?: boolean): unknown;
  getConfigs(): { spec?: unknown };
  getStore(): { replaceReducer(reducer: () => unknown): void };
};
