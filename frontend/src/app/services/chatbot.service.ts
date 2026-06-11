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
  conversation_id?: number;
  project_title: string;
  summary: string;
  steps: string[];
  available_products: ChatbotProductSuggestion[];
  unavailable_products: ChatbotProductSuggestion[];
  alternative_products: ChatbotProductSuggestion[];
  notes: string[];
}

export interface ConversationMessage {
  role: 'user' | 'bot';
  text?: string | null;
  reply?: ChatbotResponse | null;
}

export interface SavedConversation {
  id: number;
  title: string;
  updatedAt: string;
  preview?: string;
  messages?: ConversationMessage[];
}

@Injectable({
  providedIn: 'root'
})
export class ChatbotService {
  private API_URL = environment.apiBaseUrl;
  private readonly REQUEST_TIMEOUT_MS = 90000;

  async recommend(message: string, userId: number, conversationId?: number | null): Promise<ChatbotResponse> {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), this.REQUEST_TIMEOUT_MS);

    try {
      const response = await fetch(`${this.API_URL}/chatbot/recomendar`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          message,
          user_id: userId,
          conversation_id: conversationId,
        }),
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
    } catch (error: any) {
      if (error?.name === 'AbortError' || String(error?.message || '').includes('signal is aborted')) {
        throw {
          status: 408,
          message: 'La IA tardo mas de lo esperado en responder. Intenta de nuevo con una solicitud un poco mas corta.',
        };
      }

      throw error;
    } finally {
      window.clearTimeout(timeoutId);
    }
  }

  async getConversations(userId: number): Promise<SavedConversation[]> {
    const response = await fetch(`${this.API_URL}/chatbot/conversaciones/${userId}`, {
      headers: {
        'Accept': 'application/json',
      },
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(payload?.message || 'No pude cargar tus conversaciones.');
    }

    return Array.isArray(payload?.conversations) ? payload.conversations as SavedConversation[] : [];
  }

  async getConversation(userId: number, conversationId: number): Promise<SavedConversation | null> {
    const response = await fetch(`${this.API_URL}/chatbot/conversaciones/${userId}/${conversationId}`, {
      headers: {
        'Accept': 'application/json',
      },
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(payload?.message || 'No pude cargar esta conversacion.');
    }

    return payload?.conversation as SavedConversation | null;
  }

  async deleteConversation(userId: number, conversationId: number): Promise<void> {
    const response = await fetch(`${this.API_URL}/chatbot/conversaciones/${userId}/${conversationId}`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
      },
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(payload?.message || 'No pude eliminar esta conversacion.');
    }
  }
}
