import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-my-orders',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './my-orders.html',
  styleUrl: './my-orders.css'
})
export class MyOrdersComponent implements OnInit, OnDestroy {
  userName: string | null = '';
  pedidos: any[] = [];
  isLoadingOrders = false;
  ordersMessage = '';
  private retryTimeoutId: number | null = null;
  private readonly ordersUrl = `${environment.apiBaseUrl}/orders`;

  constructor(
    private authService: AuthService,
    private router: Router,
    private http: HttpClient,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.userName = localStorage.getItem('user_name') || 'Aprendiz';

    if (this.authService.isAdmin()) {
      this.router.navigate(['/productos']);
      return;
    }

    this.obtenerMisPedidos();
  }

  ngOnDestroy(): void {
    if (this.retryTimeoutId !== null) {
      window.clearTimeout(this.retryTimeoutId);
    }
  }

  obtenerMisPedidos(retryCount = 0): void {
    const userId = localStorage.getItem('user_id');

    if (!userId) {
      this.router.navigate(['/login']);
      return;
    }

    this.isLoadingOrders = true;
    this.ordersMessage = 'Cargando tus pedidos...';

    this.http.get<any>(`${this.ordersUrl}/user/${userId}`)
      .subscribe({
        next: (response) => {
          this.pedidos = response.orders || [];
          this.isLoadingOrders = false;
          this.ordersMessage = this.pedidos.length
            ? ''
            : 'Aun no has realizado pedidos.';
          this.cdr.detectChanges();
        },
        error: (error) => {
          console.error('Error obteniendo mis pedidos', error);

          if (retryCount < 5) {
            this.ordersMessage = 'Cargando tus pedidos...';
            this.retryTimeoutId = window.setTimeout(() => {
              this.obtenerMisPedidos(retryCount + 1);
            }, 800);
            return;
          }

          this.isLoadingOrders = false;
          this.ordersMessage = 'No se pudieron cargar tus pedidos. Intenta entrar nuevamente.';
          this.cdr.detectChanges();
        }
      });
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

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
