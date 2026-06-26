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
  styleUrls: ['./my-orders.css']
})
export class MyOrdersComponent implements OnInit, OnDestroy {
  userName: string | null = '';
  userPhotoUrl: string | null = null;
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
  ) { }

  ngOnInit(): void {
    this.userName = this.authService.getUserName('Aprendiz');

    if (this.authService.isAdmin()) {
      this.router.navigate(['/productos']);
      return;
    }

    this.obtenerMisPedidos();
    this.loadNavProfile();
  }

  ngOnDestroy(): void {
    if (this.retryTimeoutId !== null) {
      window.clearTimeout(this.retryTimeoutId);
    }
  }

  obtenerMisPedidos(retryCount = 0): void {
    const userId = this.authService.getUserId();

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

    const nestedProduct = item?.producto || item?.product;
    const name = item?.nombre || item?.name || item?.title || nestedProduct?.nombre || nestedProduct?.name;

    return typeof name === 'string' && name.trim() ? name.trim() : 'Producto';
  }

  getProductLine(item: any): string {
    if (typeof item === 'string') {
      return item;
    }

    const name = this.getProductName(item);
    const quantity = item?.cantidad || item?.quantity || 1;
    const subtotal = item?.subtotal;
    const discountLabel = item?.discount_label;
    const discountAmount = Number(item?.descuento || 0);
    const discountText = discountLabel
      ? ` | ${discountLabel}${discountAmount > 0 ? `, ahorro ${discountAmount} Pesos` : ''}`
      : '';

    return subtotal !== undefined && subtotal !== null
      ? `${name} x${quantity} - ${subtotal} Pesos${discountText}`
      : `${name} x${quantity}${discountText}`;
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

  getNavInitials(): string {
    return this.authService.getUserInitials('U');
  }

  private loadNavProfile(): void {
    this.userPhotoUrl = this.authService.getUserPhotoUrl();

    this.authService.loadCurrentUser().subscribe({
      next: (response) => {
        this.userPhotoUrl = response?.user?.profile_photo_url || this.authService.getUserPhotoUrl();
        this.cdr.detectChanges();
      },
      error: () => {
        this.cdr.detectChanges();
      }
    });
  }
}
