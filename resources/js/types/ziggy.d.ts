import { Config, RouteParam, RouteParamsWithQueryOverload } from 'ziggy-js';

declare global {
    function route(
        name?: string,
        params?: RouteParamsWithQueryOverload | RouteParam,
        absolute?: boolean,
        config?: Config,
    ): string;
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof route;
    }
}

export {};
