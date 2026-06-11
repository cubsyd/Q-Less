import { ChangeDetectorRef, Component, NgZone, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';
import { ChatbotResponse, ChatbotService, ConversationMessage } from '../../services/chatbot.service';

@Component({
  selector: 'app-chatbot',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './chatbot.html',
  styleUrls: ['./chatbot.css']
})
export class ChatbotComponent implements OnInit {
  private readonly welcomeMessage: ConversationMessage = {
    role: 'bot',
    text: 'Puedo ayudarte con maquetas y trabajos escolares usando el inventario real de Q-LESS. Preguntame que necesitas hacer y te dire que hay disponible, que hace falta y que alternativas puedes usar.'
  };

  prompt = '';
  isLoading = false;
  currentConversationId: number | null = null;
  conversations: ConversationMessage[] = [{ ...this.welcomeMessage }];

  constructor(
    private authService: AuthService,
    private router: Router,
    private route: ActivatedRoute,
    private chatbotService: ChatbotService,
    private ngZone: NgZone,
    private cdr: ChangeDetectorRef
  ) { }

  async ngOnInit(): Promise<void> {
    const conversationId = this.route.snapshot.queryParamMap.get('conversationId');

    if (conversationId) {
      await this.loadConversation(Number(conversationId));
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
      const response = await this.chatbotService.recommend(text, this.getCurrentUserId(), this.currentConversationId);

      this.ngZone.run(() => {
        this.currentConversationId = response.conversation_id ?? this.currentConversationId;
        this.conversations.push({
          role: 'bot',
          reply: response
        });
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

  private async loadConversation(conversationId: number): Promise<void> {
    if (!Number.isFinite(conversationId)) {
      return;
    }

    try {
      const conversation = await this.chatbotService.getConversation(this.getCurrentUserId(), conversationId);

      this.ngZone.run(() => {
        this.currentConversationId = conversation?.id ?? null;
        this.conversations = conversation?.messages?.length
          ? conversation.messages
          : [{ ...this.welcomeMessage }];
        this.cdr.detectChanges();
      });
    } catch (error) {
      console.error('Error cargando conversacion', error);
    }
  }

  private getCurrentUserId(): number {
    const userId = Number(this.authService.getUserId());

    if (!Number.isFinite(userId) || userId <= 0) {
      throw new Error('No se encontro el usuario actual.');
    }

    return userId;
  }
}
