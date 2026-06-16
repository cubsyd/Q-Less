import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';
import { ChatbotService, SavedConversation } from '../../services/chatbot.service';

@Component({
  selector: 'app-chat-conversations',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './chat-conversations.html',
  styleUrls: ['./chat-conversations.css']
})
export class ChatConversationsComponent implements OnInit {
  conversations: SavedConversation[] = [];
  message = '';

  constructor(
    private authService: AuthService,
    private chatbotService: ChatbotService,
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

  async loadConversations(): Promise<void> {
    try {
      this.conversations = await this.chatbotService.getConversations(this.getCurrentUserId());
      this.message = this.conversations.length
        ? ''
        : 'Aun no tienes conversaciones guardadas con la IA.';
    } catch (error: any) {
      console.error('Error cargando conversaciones', error);
      this.message = error?.message || 'No pude cargar tus conversaciones en este momento.';
    } finally {
      this.cdr.detectChanges();
    }
  }

  openConversation(conversation: SavedConversation): void {
    this.router.navigate(['/chatbot'], {
      queryParams: {
        conversationId: conversation.id,
      },
    });
  }

  async deleteConversation(conversation: SavedConversation, event: Event): Promise<void> {
    event.stopPropagation();

    try {
      await this.chatbotService.deleteConversation(this.getCurrentUserId(), conversation.id);
      await this.loadConversations();
    } catch (error) {
      console.error('Error eliminando conversacion', error);
      this.message = 'No pude eliminar la conversacion. Intenta de nuevo.';
      this.cdr.detectChanges();
    }
  }

  getPreview(conversation: SavedConversation): string {
    if (conversation.preview) {
      return conversation.preview;
    }

    const lastMessage = [...(conversation.messages ?? [])]
      .reverse()
      .find((message) => message.text || message.reply?.summary);

    return lastMessage?.text || lastMessage?.reply?.summary || 'Conversacion con la IA';
  }

  getDate(value: string): string {
    const date = new Date(value);

    if (!value || Number.isNaN(date.getTime())) {
      return 'Fecha no disponible';
    }

    return date.toLocaleString('es-CO', {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  }

  startNewConversation(): void {
    this.router.navigate(['/chatbot']);
  }

  logout(): void {
  this.authService.logout().subscribe({
    next: () => {
      this.router.navigate(['/login']);
    },
    error: () => {
      this.authService.clearSession();
      this.router.navigate(['/login']);
    }
  });
}

  private getCurrentUserId(): number {
    const userId = Number(this.authService.getUserId());

    if (!Number.isFinite(userId) || userId <= 0) {
      throw new Error('No se encontro el usuario actual.');
    }

    return userId;
  }
}
