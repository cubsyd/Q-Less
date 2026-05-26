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
  styleUrl: './orders.css'
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

    this.userName =
      localStorage.getItem('user_name') || 'Administrador';

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

  getRemainingTime(expiresAt: string): string {

    const expiration =
      new Date(expiresAt).getTime();

    const now =
      new Date().getTime();

    const difference =
      expiration - now;

    if (difference <= 0) {
      return 'Expirado';
    }

    const minutes =
      Math.floor(difference / 1000 / 60);

    const seconds =
      Math.floor((difference / 1000) % 60);

    return `${minutes}:${seconds
      .toString()
      .padStart(2, '0')}`;
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

  iniciarVerificadorExpiraciones(): void {

    if (this.expirationIntervalId !== null) {
      return;
    }

    this.expirationIntervalId = window.setInterval(() => {

      this.pedidos.forEach((pedido) => {

        const expiration =
          new Date(pedido.expires_at).getTime();

        const now =
          new Date().getTime();

        if (
          expiration <= now &&
          pedido.status === 'pendiente'
        ) {

          this.http.patch(

            `${this.ordersUrl}/${pedido.id}/status`,

            {
              status: 'expirado'
            }

          ).subscribe({

            next: () => {

              pedido.status = 'expirado';
              this.cdr.detectChanges();
            }
          });
        }
      });

    }, 1000);
  }

  marcarEntregado(pedido: any): void {

    this.http.patch(

      `${this.ordersUrl}/${pedido.id}/status`,

      {
        status: 'entregado'
      }

    ).subscribe({

      next: () => {

        pedido.status = 'entregado';
        this.cdr.detectChanges();
      },

      error: (error) => {

        console.error(
          'Error actualizando pedido',
          error
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

      next: () => {

        pedido.status = 'no_entregado';
        this.cdr.detectChanges();
      },

      error: (error) => {

        console.error(
          'Error actualizando pedido',
          error
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
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
