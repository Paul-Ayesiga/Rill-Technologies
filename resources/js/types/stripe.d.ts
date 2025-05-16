/**
 * Type definitions for Stripe.js
 */

interface Window {
  Stripe: any;
}

declare namespace Stripe {
  interface StripeInstance {
    elements: (options?: any) => StripeElements;
    createPaymentMethod: (options: any) => Promise<{
      paymentMethod?: any;
      error?: StripeError;
    }>;
    confirmCardPayment: (clientSecret: string, options: any) => Promise<{
      paymentIntent?: any;
      error?: StripeError;
    }>;
    confirmCardSetup: (clientSecret: string, options: any) => Promise<{
      setupIntent?: any;
      error?: StripeError;
    }>;
  }

  interface StripeElements {
    create: (type: string, options?: any) => StripeElement;
  }

  interface StripeElement {
    mount: (domElement: string | HTMLElement) => void;
    unmount: () => void;
    on: (event: string, handler: (event: any) => void) => void;
    update: (options: any) => void;
  }

  interface StripeError {
    type: string;
    message: string;
    code?: string;
    decline_code?: string;
    param?: string;
  }
}
