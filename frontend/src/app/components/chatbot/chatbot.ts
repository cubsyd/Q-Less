import { ChangeDetectorRef, Component, NgZone, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';
import { ChatbotResponse, ChatbotService } from '../../services/chatbot.service';

interface ConversationMessage {
  role: 'user' | 'bot';
  text?: string;
  reply?: ChatbotResponse;
}

interface SavedConversation {
  id: string;
  title: string;
  updatedAt: string;
  messages: ConversationMessage[];
}

@Component({
  selector: 'app-chatbot',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './chatbot.html',
  styleUrl: './chatbot.css'
})
export class ChatbotComponent implements OnInit {
  private readonly welcomeMessage: ConversationMessage = {
    role: 'bot',
    text: 'Puedo ayudarte con maquetas y trabajos escolares usando el inventario real de Q-LESS. Preguntame que necesitas hacer y te dire que hay disponible, que hace falta y que alternativas puedes usar.'
  };

  prompt = '';
  isLoading = false;
  currentConversationId: string | null = null;
  conversations: ConversationMessage[] = [{ ...this.welcomeMessage }];

  constructor(
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute,
    private chatbotService: ChatbotService,
    private ngZone: NgZone,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    const conversationId = this.route.snapshot.queryParamMap.get('conversationId');

    if (conversationId) {
      this.loadConversation(conversationId);
    }
  }

  async sendMessage(): Promise<void> {
    const text = this.prompt.trim();
    if (!text || this.isLoading) {
      return;
    }

    this.conversations.push({ role: 'user', text });
    this.prompt = '';
    this.isLoading = true;

    try {
      const response = await this.chatbotService.recommend(text);

      this.ngZone.run(() => {
        this.conversations.push({
          role: 'bot',
          reply: response
        });
        this.saveCurrentConversation(text);
        this.isLoading = false;
        this.cdr.detectChanges();
      });
    } catch (error: any) {
      console.error('Error consultando chatbot', error);

      const backendMessage =
        error?.error?.message
        || error?.message
        || 'No pude consultar el inventario en este momento.';

      this.ngZone.run(() => {
        this.conversations.push({
          role: 'bot',
          text: `${backendMessage} Verifica que el backend este corriendo e intenta de nuevo.`
        });
        this.saveCurrentConversation(text);
        this.isLoading = false;
        this.cdr.detectChanges();
      });
    }
  }

  trackByIndex(index: number): number {
    return index;
  }

  get isAdmin(): boolean {
    return this.authService.isAdmin();
  }

  startNewConversation(): void {
    this.currentConversationId = null;
    this.conversations = [{ ...this.welcomeMessage }];
    this.router.navigate(['/chatbot']);
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  private loadConversation(conversationId: string): void {
    const conversation = this.getSavedConversations()
      .find((item) => item.id === conversationId);

    if (!conversation) {
      return;
    }

    this.currentConversationId = conversation.id;
    this.conversations = conversation.messages?.length
      ? conversation.messages
      : [{ ...this.welcomeMessage }];
  }

  private saveCurrentConversation(firstUserText: string): void {
    const savedConversations = this.getSavedConversations();
    const conversationId = this.currentConversationId || this.createConversationId();
    const existingIndex = savedConversations.findIndex((item) => item.id === conversationId);
    const title = this.buildConversationTitle(firstUserText);
    const conversation: SavedConversation = {
      id: conversationId,
      title: existingIndex >= 0 ? savedConversations[existingIndex].title : title,
      updatedAt: new Date().toISOString(),
      messages: this.conversations,
    };

    if (existingIndex >= 0) {
      savedConversations[existingIndex] = conversation;
    } else {
      savedConversations.unshift(conversation);
    }

    this.currentConversationId = conversationId;
    localStorage.setItem(this.storageKey(), JSON.stringify(savedConversations.slice(0, 30)));
  }

  private getSavedConversations(): SavedConversation[] {
    try {
      const rawValue = localStorage.getItem(this.storageKey());
      const parsed = rawValue ? JSON.parse(rawValue) : [];

      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }

  private storageKey(): string {
    const userId = localStorage.getItem('user_id') || 'anonimo';
    return `qless_chat_conversations_${userId}`;
  }

  private createConversationId(): string {
    return `chat_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
  }

  private buildConversationTitle(text: string): string {
    return text.length > 44 ? `${text.slice(0, 44)}...` : text;
  }
}
