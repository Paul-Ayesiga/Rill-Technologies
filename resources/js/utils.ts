/**
 * Updates a ref value based on an updater function or direct value
 * This is a utility function for TanStack Table state management
 * 
 * @param updaterOrValue - Either a function that takes the current value and returns a new value, or a direct value
 * @param ref - The ref object to update
 */
export function valueUpdater<T>(
  updaterOrValue: ((old: T) => T) | T,
  ref: { value: T }
): void {
  ref.value = typeof updaterOrValue === 'function'
    ? (updaterOrValue as (old: T) => T)(ref.value)
    : updaterOrValue;
}
