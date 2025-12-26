import { Config as ZiggyConfig } from 'ziggy-js';

declare global {
  interface Window {
    Ziggy: ZiggyConfig;
  }

  function route(): ZiggyConfig;
  function route(name: string, params?: any, absolute?: boolean): string;
}

export {};
