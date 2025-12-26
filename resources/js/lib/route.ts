import { route as ziggyRoute } from 'ziggy-js';
import type { Config as ZiggyConfig } from 'ziggy-js';

declare global {
  interface Window {
    Ziggy: ZiggyConfig;
  }
}

export function route(name?: string, params?: any, absolute?: boolean): any {
  if (typeof window !== 'undefined' && window.Ziggy && name) {
    return ziggyRoute(name, params, absolute, window.Ziggy);
  }
  return '';
}
