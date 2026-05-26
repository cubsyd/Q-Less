import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, throwError } from 'rxjs';
import { tap } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface CartItem {
  id: number;
  nombre: string;
  precio: number;
  image_path?: string | null;
  cantidad: number;
  stock_available?: number;
  expires_at?: string | null;
  remaining_seconds?: number;
}

interface CartResponse {
  status: boolean;
  items: CartItem[];
  count: number;
  total: number;
}

interface PaymentPreferenceResponse {
  status: boolean;
  message: string;
  external_reference: string;
  preference_id: string;
  init_point: string | null;
  sandbox_init_point: string | null;
}

@Injectable({
  providedIn: 'root'
})
export class CartService {
  private readonly API_URL = `${environment.apiBaseUrl}/carrito`;
  private readonly PAYMENT_URL = `${environment.apiBaseUrl}/payments`;
  private readonly itemsSubject = new BehaviorSubject<CartItem[]>([]);

  readonly items$ = this.itemsSubject.asObservable();

  constructor(private http: HttpClient) {}

  getItems(): CartItem[] {
    return this.itemsSubject.value;
  }

  syncCurrentUser(): void {
    const userId = this.getCurrentUserId();

    if (!userId) {
      this.itemsSubject.next([]);
      return;
    }

    this.http.get<CartResponse>(`${this.API_URL}/${userId}`).subscribe({
      next: (response) => this.itemsSubject.next(response.items || []),
      error: () => this.itemsSubject.next([]),
    });
  }

  getCount(): number {
    return this.getItems().reduce((total, item) => total + item.cantidad, 0);
  }

  getTotal(): number {
    return this.getItems().reduce((total, item) => total + (item.precio * item.cantidad), 0);
  }

  hasItem(productId: number): boolean {
    return this.getItems().some(item => item.id === productId);
  }

  addItem(product: any): Observable<CartResponse> {
    const userId = this.getCurrentUserId();

    if (!userId) {
      return throwError(() => new Error('Debes iniciar sesion para usar el carrito.'));
    }

    return this.http.post<CartResponse>(`${this.API_URL}/reservar`, {
      user_id: userId,
      producto_id: product.id,
    }).pipe(
      tap((response) => this.itemsSubject.next(response.items || []))
    );
  }

  removeItem(id: number): Observable<CartResponse> {
    const userId = this.getCurrentUserId();

    if (!userId) {
      return throwError(() => new Error('Debes iniciar sesion para usar el carrito.'));
    }

    return this.http.delete<CartResponse>(`${this.API_URL}/${userId}/${id}`).pipe(
      tap((response) => this.itemsSubject.next(response.items || []))
    );
  }

  decreaseItem(id: number): Observable<CartResponse> {
    const userId = this.getCurrentUserId();

    if (!userId) {
      return throwError(() => new Error('Debes iniciar sesion para usar el carrito.'));
    }

    return this.http.patch<CartResponse>(`${this.API_URL}/${userId}/${id}/decrease`, {}).pipe(
      tap((response) => this.itemsSubject.next(response.items || []))
    );
  }

  clear(): Observable<CartResponse> {
    const userId = this.getCurrentUserId();

    if (!userId) {
      return throwError(() => new Error('Debes iniciar sesion para usar el carrito.'));
    }

    return this.http.delete<CartResponse>(`${this.API_URL}/${userId}`).pipe(
      tap((response) => this.itemsSubject.next(response.items || []))
    );
  }

  checkout(paymentData: { nombre: string; documento: string; metodo: string; referencia: string; }): Observable<CartResponse> {
    const userId = this.getCurrentUserId();

    if (!userId) {
      return throwError(() => new Error('Debes iniciar sesion para pagar.'));
    }

    return this.http.post<CartResponse>(`${this.API_URL}/checkout`, {
      user_id: userId,
      ...paymentData,
    }).pipe(
      tap((response) => this.itemsSubject.next(response.items || []))
    );
  }

  createPaymentPreference(): Observable<PaymentPreferenceResponse> {
    const userId = this.getCurrentUserId();

    if (!userId) {
      return throwError(() => new Error('Debes iniciar sesion para pagar.'));
    }

    return this.http.post<PaymentPreferenceResponse>(`${this.PAYMENT_URL}/preference`, {
      user_id: userId,
    });
  }

  createOrder(userId: number, paymentData: { payment_provider?: string; payment_reference?: string; payment_status?: string } = {}) {
    return this.http.post(`${this.PAYMENT_URL}/create-order`, {
      user_id: userId,
      ...paymentData,
    });
  }

  private getCurrentUserId(): number | null {
    const userId = localStorage.getItem('user_id');
    return userId ? Number(userId) : null;
  }
}
