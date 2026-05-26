import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';

interface SavedConversation {
  id: string;
  title: string;
  updatedAt: string;
  messages: any[];
}

@Component({
  selector: 'app-chat-conversations',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './chat-conversations.html',
  styleUrl: './chat-conversations.css'
})
export class ChatConversationsComponent implements OnInit {
  conversations: SavedConversation[] = [];
  message = '';

  constructor(
    private authService: AuthService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    if (this.authService.isAdmin()) {
      this.router.navigate(['/productos']);
      return;
    }

    this.loadConversations();
  }

  loadConversations(): void {
    this.conversations = this.getSavedConversations();
    this.message = this.conversations.length
      ? ''
      : 'Aun no tienes conversaciones guardadas con la IA.';
    this.cdr.detectChanges();
  }

  openConversation(conversation: SavedConversation): void {
    this.router.navigate(['/chatbot'], {
      queryParams: {
        conversationId: conversation.id,
      },
    });
  }

  deleteConversation(conversation: SavedConversation, event: Event): void {
    event.stopPropagation();

    const conversations = this.getSavedConversations()
      .filter((item) => item.id !== conversation.id);

    localStorage.setItem(this.storageKey(), JSON.stringify(conversations));
    this.loadConversations();
  }

  getPreview(conversation: SavedConversation): string {
    const lastMessage = [...(conversation.messages || [])]
      .reverse()
      .find((message) => message.text || message.reply?.summary);

    return lastMessage?.text || lastMessage?.reply?.summary || 'Conversacion con la IA';
  }

  getDate(value: string): string {
    return new Date(value).toLocaleString('es-CO', {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  }

  startNewConversation(): void {
    this.router.navigate(['/chatbot']);
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
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
}