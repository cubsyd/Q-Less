import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ConnectionStatusService {
  private readonly offlineMessage = 'No se pudo conectar con internet. Revisa la conexion de tu dispositivo e intenta nuevamente.';
  private readonly messageSubject = new BehaviorSubject<string | null>(null);

  readonly message$ = this.messageSubject.asObservable();

  constructor() {
    if (typeof window === 'undefined') {
      return;
    }

    if (!navigator.onLine) {
      this.showOfflineMessage();
    }

    window.addEventListener('offline', () => this.showOfflineMessage());
    window.addEventListener('online', () => this.clearMessage());
  }

  showOfflineMessage(message = this.offlineMessage): void {
    this.messageSubject.next(message);
  }

  clearMessage(): void {
    this.messageSubject.next(null);
  }
}
