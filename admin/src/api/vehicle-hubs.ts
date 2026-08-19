import { getAdminConfig } from './article-packages';
import {
  CreateVehicleHubResponse,
  HubCreatePreview,
  VehicleHubHealth,
  VehicleHubRecord,
} from '../types/public-seo';

async function apiGet<T>(endpoint: string): Promise<T> {
  const config = getAdminConfig();
  const response = await fetch(`${config.restUrl}${endpoint}`, {
    headers: { 'X-WP-Nonce': config.nonce },
  });
  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as { message?: string };
    throw new Error(body.message ?? `Request failed (${response.status}).`);
  }
  return (await response.json()) as T;
}

async function apiPost<T>(endpoint: string, payload?: unknown): Promise<T> {
  const config = getAdminConfig();
  const response = await fetch(`${config.restUrl}${endpoint}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
    },
    body: JSON.stringify(payload ?? {}),
  });
  const data = (await response.json()) as T & { message?: string };
  if (!response.ok) {
    throw new Error(data.message ?? `Request failed (${response.status}).`);
  }
  return data;
}

export async function fetchVehicleHubs(): Promise<VehicleHubRecord[]> {
  return apiGet<VehicleHubRecord[]>('/vehicle-hubs');
}

export async function fetchVehicleHub(hubId: number): Promise<VehicleHubRecord> {
  return apiGet<VehicleHubRecord>(`/vehicle-hubs/${hubId}`);
}

export async function fetchVehicleHubHealth(hubId: number): Promise<VehicleHubHealth> {
  return apiGet<VehicleHubHealth>(`/vehicle-hubs/${hubId}/health`);
}

export async function fetchHubCreatePreview(vehicleLabel: string): Promise<HubCreatePreview> {
  return apiGet<HubCreatePreview>(
    `/vehicle-hubs/preview?vehicle=${encodeURIComponent(vehicleLabel)}`,
  );
}

export async function createVehicleHubDraft(
  vehicleLabel: string,
): Promise<CreateVehicleHubResponse> {
  return apiPost<CreateVehicleHubResponse>('/vehicle-hubs', { vehicle_label: vehicleLabel });
}
