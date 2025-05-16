/**
 * Service for fetching and tracking ads
 */

interface Ad {
  id: number;
  ad_id: string;
  type: string;
  position: string | null;
  image_url: string | null;
  title: string | null;
  description: string | null;
  url: string | null;
  settings: Record<string, any> | null;
}

/**
 * Fetch active ads for a specific page
 *
 * @param page The page to fetch ads for (e.g., 'welcome', 'pricing', 'dashboard')
 * @returns Promise with the ads
 */
export async function fetchAds(page: string = 'all'): Promise<Ad[]> {
  try {
    const response = await fetch(`/ads/active?page=${page}`, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      }
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();
    return data.ads || [];
  } catch (error) {
    console.error('Error fetching ads:', error);
    return [];
  }
}

/**
 * Track ad click
 *
 * @param adId The unique ad ID to track
 * @returns Promise with the tracking result
 */
export async function trackAdClick(adId: string): Promise<boolean> {
  try {
    const response = await fetch('/ads/track-click', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({ ad_id: adId })
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();
    return data.success || false;
  } catch (error) {
    console.error('Error tracking ad click:', error);
    return false;
  }
}

/**
 * Get ads for a specific page and type
 *
 * @param ads Array of ads
 * @param type The ad type to filter by (e.g., 'banner', 'floating', 'top')
 * @param position Optional position to filter by (e.g., 'left', 'right')
 * @returns Filtered ads
 * @deprecated Use the AdDisplay component instead which handles filtering internally
 */
export function getAdsByType(ads: Ad[], type: string, position?: string): Ad[] {
  console.log(`Filtering ads by type: ${type}, position: ${position || 'any'}`);
  console.log('Available ads:', ads);

  return ads.filter(ad => {
    if (ad.type !== type) {
      console.log(`Ad ${ad.id} type ${ad.type} doesn't match ${type}`);
      return false;
    }

    if (position && ad.position !== position) {
      console.log(`Ad ${ad.id} position ${ad.position} doesn't match ${position}`);
      return false;
    }

    console.log(`Ad ${ad.id} matches criteria`);
    return true;
  });
}
