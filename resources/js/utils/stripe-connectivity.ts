/**
 * Utility functions for handling Stripe connectivity issues
 * Provides fallback messages and error handling for Stripe.js
 */

import { toast } from 'vue-sonner';

/**
 * Check if the browser is online
 * @returns {boolean} True if online, false if offline
 */
export const isOnline = (): boolean => {
  return typeof navigator !== 'undefined' && navigator.onLine;
};

/**
 * Load the Stripe.js script with error handling
 * @param {Function} onError Callback function to handle errors
 * @returns {Promise<void>}
 */
export const loadStripeScript = (onError: (message: string) => void): Promise<void> => {
  return new Promise((resolve, reject) => {
    try {
      // Check if we're in a browser environment
      if (typeof document === 'undefined') {
        const error = 'Document is not defined, cannot load Stripe script';
        console.error(error);
        onError('Payment system could not be initialized. Please try again later.');
        reject(new Error(error));
        return;
      }

      // Check for internet connectivity
      if (!isOnline()) {
        const error = 'No internet connection detected when loading Stripe script';
        console.error(error);
        onError('No internet connection. Please check your connection and try again.');
        reject(new Error(error));
        return;
      }

      // Create and append the script
      const script = document.createElement('script');
      script.src = 'https://js.stripe.com/v3/';
      script.async = true;

      // Handle successful loading
      script.onload = () => {
        resolve();
      };

      // Handle loading errors
      script.onerror = () => {
        const error = 'Failed to load Stripe.js script';
        console.error(error);
        onError('Unable to connect to payment service. Please check your internet connection and try again.');
        reject(new Error(error));
      };

      document.head.appendChild(script);
    } catch (error) {
      console.error('Error loading Stripe script:', error);
      onError('Error loading payment system. Please check your internet connection and try again.');
      reject(error);
    }
  });
};

/**
 * Initialize Stripe with error handling
 * @param {string} stripeKey The Stripe publishable key
 * @param {Function} onError Callback function to handle errors
 * @returns {Promise<any>} The Stripe instance or null if there was an error
 */
export const initializeStripe = (stripeKey: string, onError: (message: string) => void): Promise<any> => {
  return new Promise((resolve, reject) => {
    try {
      // Check if we're in a browser environment
      if (typeof window === 'undefined') {
        const error = 'Window is not defined, cannot initialize Stripe';
        console.error(error);
        onError('Payment system could not be initialized. Please try again later.');
        reject(new Error(error));
        return;
      }

      // Check if Stripe is loaded
      if (!window.Stripe) {
        const error = 'Stripe.js is not loaded';
        console.error(error);
        onError('Payment system is not available. Please try again later.');
        reject(new Error(error));
        return;
      }

      // Check for internet connectivity
      if (!isOnline()) {
        const error = 'No internet connection detected';
        console.error(error);
        onError('No internet connection. Please check your connection and try again.');
        reject(new Error(error));
        return;
      }

      // Initialize Stripe
      try {
        const stripe = window.Stripe(stripeKey);
        resolve(stripe);
      } catch (stripeError) {
        console.error('Error initializing Stripe object:', stripeError);
        onError('Unable to connect to payment service. Please check your internet connection and try again.');
        reject(stripeError);
      }
    } catch (error) {
      console.error('Unexpected error in initializeStripe:', error);
      onError('An unexpected error occurred. Please check your internet connection and try again.');
      reject(error);
    }
  });
};

/**
 * Show a toast notification for Stripe connectivity errors
 * @param {string} message The error message to display
 */
export const showStripeConnectivityError = (message: string): void => {
  toast.error('Payment System Unavailable', {
    description: message || 'Unable to connect to payment service. Please check your internet connection and try again.',
  });
};

/**
 * Check if a Stripe API error is related to connectivity
 * @param {any} error The error object from Stripe
 * @returns {boolean} True if it's a connectivity error, false otherwise
 */
export const isConnectivityError = (error: any): boolean => {
  if (!error) return false;
  
  // Check for network errors
  if (!isOnline()) return true;
  
  // Check for Stripe-specific network errors
  if (error.type === 'network_error' || error.type === 'api_connection_error') return true;
  
  // Check for error message content
  if (error.message && (
    error.message.includes('network') || 
    error.message.includes('connection') || 
    error.message.includes('connect')
  )) return true;
  
  return false;
};

/**
 * Handle Stripe API errors with appropriate messages
 * @param {any} error The error object from Stripe
 * @returns {string} A user-friendly error message
 */
export const getStripeErrorMessage = (error: any): string => {
  if (!error) return 'An unknown error occurred. Please try again.';
  
  if (isConnectivityError(error)) {
    return 'Unable to connect to payment service. Please check your internet connection and try again.';
  }
  
  // Return the error message from Stripe if available
  return error.message || 'There was an error processing your payment. Please try again.';
};
