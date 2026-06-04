import { Injectable } from '@angular/core';
import { environment } from '../../environments/environment';

export interface ChatbotProductSuggestion {
  id: number;
  nombre: string;
  descripcion: string;
  precio: number;
  stock: number;
  categoria: string;
  image_path: string | null;
  reason: string;
}

export interface ChatbotResponse {
  status: boolean;
  query: string;
  project_title: string;
  summary: string;
  steps: string[];
  available_products: ChatbotProductSuggestion[];
  unavailable_products: ChatbotProductSuggestion[];
  alternative_products: ChatbotProductSuggestion[];
  notes: string[];
}

@Injectable({
  providedIn: 'root'
})
export class ChatbotService {
  private API_URL = `${environment.apiBaseUrl}/chatbot/recomendar`;

  async recommend(message: string): Promise<ChatbotResponse> {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 20000);

    try {
      const response = await fetch(this.API_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ message }),
        signal: controller.signal,
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw {
          status: response.status,
          error: payload,
          message: payload?.message || 'No pude consultar el chatbot.',
        };
      }

      return payload as ChatbotResponse;
    } finally {
      window.clearTimeout(timeoutId);
    }
  }
}
