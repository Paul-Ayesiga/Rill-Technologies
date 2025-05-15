import { Echo as LaravelEcho } from 'laravel-echo';

declare global {
  interface Window {
    Echo: LaravelEcho;
    Pusher: any;
  }
}
