import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-orders',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './orders.html',
  styleUrls: ['./orders.css']
})
export class OrdersComponent implements OnInit, OnDestroy {

  userName: string | null = '';

  pedidos: any[] = [];
  isLoadingOrders = false;
  ordersMessage = '';
  deletingOrders = new Set<number>();
  private expirationIntervalId: number | null = null;
  private retryTimeoutId: number | null = null;
  private readonly ordersUrl = `${environment.apiBaseUrl}/orders`;

  constructor(
    private authService: AuthService,
    private router: Router,
    private http: HttpClient,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {

    this.userName = this.authService.getUserName('Administrador');

    if (!this.authService.isAdmin()) {
      this.router.navigate(['/home']);
      return;
    }

    this.obtenerPedidos();
    this.iniciarVerificadorExpiraciones();
  }

  ngOnDestroy(): void {
    if (this.expirationIntervalId !== null) {
      window.clearInterval(this.expirationIntervalId);
    }

    if (this.retryTimeoutId !== null) {
      window.clearTimeout(this.retryTimeoutId);
    }
  }

  obtenerPedidos(retryCount = 0): void {

    this.isLoadingOrders = true;
    this.ordersMessage = 'Cargando pedidos...';

    this.http.get<any>(this.ordersUrl)
      .subscribe({

        next: (response) => {

          this.pedidos = response.orders || [];
          this.isLoadingOrders = false;
          this.ordersMessage = this.pedidos.length
            ? ''
            : 'No hay pedidos disponibles.';
          this.cdr.detectChanges();
        },

        error: (error) => {

          console.error(
            'Error obteniendo pedidos',
            error
          );

          if (retryCount < 5) {
            this.ordersMessage = 'Cargando pedidos...';
            this.retryTimeoutId = window.setTimeout(() => {
              this.obtenerPedidos(retryCount + 1);
            }, 800);
            return;
          }

          this.isLoadingOrders = false;
          this.ordersMessage =
            'No se pudieron cargar los pedidos. Intenta entrar nuevamente.';
          this.cdr.detectChanges();
        }
      });
  }

  getRemainingTime(pedido: any): string {
    const remaining = this.getRemainingSeconds(pedido);

    if (remaining <= 0 && pedido?.status !== 'pendiente') {
      return 'Expirado';
    }

    if (remaining <= 0) {
      return '0:00';
    }

    const minutes =
      Math.floor(remaining / 60);

    const seconds =
      remaining % 60;

    return `${minutes}:${seconds
      .toString()
      .padStart(2, '0')}`;
  }

  isOrderExpired(pedido: any): boolean {
    return this.getRemainingSeconds(pedido) <= 0;
  }

  canMarkDelivered(pedido: any): boolean {
    return pedido.status === 'pendiente';
  }

  canMarkNotDelivered(pedido: any): boolean {
    return ['pendiente', 'entregado'].includes(pedido.status);
  }

  getProductName(item: any): string {
    if (typeof item === 'string') {
      return item;
    }

    return item?.nombre || 'Producto';
  }

  getProductLine(item: any): string {
    if (typeof item === 'string') {
      return item;
    }

    const name = item?.nombre || 'Producto';
    const quantity = item?.cantidad || 1;
    const subtotal = item?.subtotal;

    return subtotal !== undefined
      ? `${name} x${quantity} - ${subtotal} Pesos`
      : `${name} x${quantity}`;
  }

  private updateLocalOrder(updatedOrder: any): void {
    if (!updatedOrder?.id) {
      return;
    }

    this.pedidos = this.pedidos.map((pedido) =>
      pedido.id === updatedOrder.id ? updatedOrder : pedido
    );
  }

  private showOrderError(error: any, fallback: string): void {
    this.ordersMessage = error?.error?.message || fallback;

    if (error?.error?.order) {
      this.updateLocalOrder(error.error.order);
    }

    this.cdr.detectChanges();
  }

  iniciarVerificadorExpiraciones(): void {

    if (this.expirationIntervalId !== null) {
      return;
    }

    this.expirationIntervalId = window.setInterval(() => {

      this.pedidos = this.pedidos.map((pedido) => ({
        ...pedido,
        remaining_seconds: Math.max(0, this.getRemainingSeconds(pedido) - 1),
      }));

      this.cdr.detectChanges();

    }, 1000);
  }

  marcarEntregado(pedido: any): void {

    this.http.patch(

      `${this.ordersUrl}/${pedido.id}/status`,

      {
        status: 'entregado'
      }

    ).subscribe({

      next: (response: any) => {

        this.ordersMessage = response?.message || '';
        this.updateLocalOrder(response.order || { ...pedido, status: 'entregado' });
        this.cdr.detectChanges();
      },

      error: (error) => {

        console.error('Error actualizando pedido', error);
        this.showOrderError(
          error,
          'No se pudo marcar el pedido como entregado.'
        );
      }
    });
  }

  marcarNoEntregado(pedido: any): void {

    this.http.patch(

      `${this.ordersUrl}/${pedido.id}/status`,

      {
        status: 'no_entregado'
      }

    ).subscribe({

      next: (response: any) => {

        this.ordersMessage = response?.message || '';
        this.updateLocalOrder(response.order || { ...pedido, status: 'no_entregado' });
        this.cdr.detectChanges();
      },

      error: (error) => {

        console.error('Error actualizando pedido', error);
        this.showOrderError(
          error,
          'No se pudo marcar el pedido como no entregado.'
        );
      }
    });
  }

  eliminarPedido(pedido: any): void {

    this.deletingOrders.add(pedido.id);
    this.pedidos =
      this.pedidos.filter((item) => item.id !== pedido.id);
    this.ordersMessage = this.pedidos.length
      ? ''
      : 'No hay pedidos disponibles.';
    this.cdr.detectChanges();

    this.http.delete(

      `${this.ordersUrl}/${pedido.id}`

    ).subscribe({

      next: () => {

        this.deletingOrders.delete(pedido.id);
      },

      error: (error) => {

        console.error(
          'Error eliminando pedido',
          error
        );

        this.deletingOrders.delete(pedido.id);
        this.ordersMessage =
          'El pedido se quito de la lista, pero no se pudo eliminar en el servidor.';
        this.cdr.detectChanges();
      }
    });
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

  private getRemainingSeconds(pedido: any): number {
    const remainingSeconds = Number(pedido?.remaining_seconds);

    if (Number.isFinite(remainingSeconds)) {
      return Math.max(0, Math.floor(remainingSeconds));
    }

    const expiresAt = this.parseOrderDate(pedido?.expires_at_iso || pedido?.expires_at);

    if (!expiresAt) {
      return 0;
    }

    return Math.max(0, Math.floor((expiresAt.getTime() - Date.now()) / 1000));
  }

  private parseOrderDate(value: string | null | undefined): Date | null {
    if (!value) {
      return null;
    }

    const hasTimezone = /([zZ]|[+-]\d{2}:?\d{2})$/.test(value);
    const normalized = value.includes('T') || hasTimezone
      ? value
      : value.replace(' ', 'T');
    const date = new Date(normalized);

    return Number.isNaN(date.getTime()) ? null : date;
  }
}
