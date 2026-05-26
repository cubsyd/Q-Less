import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';
import { CartItem, CartService } from '../../services/cart.service';

@Component({
  selector: 'app-cart',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './cart.html',
  styleUrl: './cart.css'
})
export class CartComponent implements OnInit, OnDestroy {
  cartItems: CartItem[] = [];
  total = 0;
  cartMessage = '';
  isRedirectingToPayment = false;
  private countdownIntervalId: number | null = null;
  loadingItems = new Set<number>();

  constructor(
    private authService: AuthService,
    private cartService: CartService,
    private route: ActivatedRoute,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    this.route.queryParamMap.subscribe(params => {
      const paymentStatus = params.get('payment');

      if (paymentStatus === 'success') {

        this.cartMessage =
          'Pago aprobado en Mercado Pago. Tu compra esta siendo confirmada.';

        const userId = localStorage.getItem('user_id');

        if (userId) {

          this.cartMessage =
            'Pago simulado aprobado. Si el pedido ya fue creado, puedes revisarlo en Mis pedidos.';
        }
      }
    });

    this.cartService.syncCurrentUser();
    this.cartService.items$.subscribe(items => {
      this.cartItems = items.map(item => ({ ...item }));
      this.total = this.cartService.getTotal();
      this.cdr.detectChanges();
    });

    this.countdownIntervalId = window.setInterval(() => {
      this.cartItems = this.cartItems.map(item => {
        const remaining = Math.max(0, (item.remaining_seconds || 0) - 1);

        return {
          ...item,
          remaining_seconds: remaining,
        };
      });

      this.cdr.detectChanges();
    }, 1000);
  }

  ngOnDestroy(): void {
    if (this.countdownIntervalId !== null) {
      window.clearInterval(this.countdownIntervalId);
    }
  }

  removeItem(id: number): void {
    this.cartMessage = '';
    this.loadingItems.add(id);
    this.cartService.removeItem(id).subscribe({
      next: () => {
        this.total = this.cartService.getTotal();
        this.loadingItems.delete(id);
      },
      error: (error) => {
        console.error('Error quitando producto del carrito', error);
        this.loadingItems.delete(id);
      }
    });
  }

  increaseItem(item: CartItem): void {
    this.cartMessage = '';
    this.loadingItems.add(item.id);
    this.cartService.addItem(item).subscribe({
      next: () => {
        this.total = this.cartService.getTotal();
        this.loadingItems.delete(item.id);
      },
      error: (error) => {
        console.error('Error aumentando cantidad', error);
        this.cartMessage = error?.error?.errors?.producto_id?.[0]
          || error?.error?.errors?.carrito?.[0]
          || error?.error?.message
          || 'No se pudo aumentar la cantidad del producto.';
        this.loadingItems.delete(item.id);
      }
    });
  }

  decreaseItem(id: number): void {
    this.cartMessage = '';
    this.loadingItems.add(id);
    this.cartService.decreaseItem(id).subscribe({
      next: () => {
        this.total = this.cartService.getTotal();
        this.loadingItems.delete(id);
      },
      error: (error) => {
        console.error('Error disminuyendo cantidad', error);
        this.loadingItems.delete(id);
      }
    });
  }

  canIncrease(item: CartItem): boolean {
    return !this.loadingItems.has(item.id) && (item.stock_available || 0) > 0;
  }

  clearCart(): void {
    this.cartMessage = '';
    this.cartService.clear().subscribe({
      next: () => {
        this.total = this.cartService.getTotal();
      },
      error: (error) => {
        console.error('Error vaciando carrito', error);
      }
    });
  }

  checkout(): void {

    if (this.cartItems.length === 0 || this.isRedirectingToPayment) {
      return;
    }

    this.cartMessage = '';
    this.isRedirectingToPayment = true;
    const paymentWindow = window.open('', '_blank');

    if (!paymentWindow) {
      this.isRedirectingToPayment = false;
      this.cartMessage =
        'El navegador bloqueo la ventana de Mercado Pago. Permite ventanas emergentes para continuar.';
      return;
    }

    paymentWindow.document.write('Redirigiendo a Mercado Pago...');

    this.cartService.createPaymentPreference().subscribe({

      next: (response: any) => {

        const paymentUrl =
          response.init_point ||
          response.sandbox_init_point;

        if (!paymentUrl) {

          console.error('Mercado Pago no devolvio URL');

          this.isRedirectingToPayment = false;
          paymentWindow.close();

          this.cartMessage =
            'Mercado Pago no devolvio una URL de pago valida.';

          return;
        }

        const userId = localStorage.getItem('user_id');

        if (userId) {

          this.cartService.createOrder(Number(userId), {
            payment_provider: 'mercadopago_simulado',
            payment_reference: response.external_reference || response.preference_id || null,
            payment_status: 'simulado',
          })
            .subscribe({

              next: (orderResponse: any) => {

                this.cartMessage =
                  `Pedido #${orderResponse.order.order_number} creado correctamente.`;

                paymentWindow.location.href = paymentUrl;

                this.isRedirectingToPayment = false;
              },

              error: (error: any) => {

                console.error('ERROR CREANDO PEDIDO:', error);

                this.cartMessage =
                  'No se pudo crear el pedido.';

                this.isRedirectingToPayment = false;
                paymentWindow.close();
              }
            });

        } else {

          this.cartMessage =
            'Usuario no encontrado.';

          this.isRedirectingToPayment = false;
          paymentWindow.close();
        }
      },

      error: (error) => {

        console.error('ERROR COMPLETO:', error);

        console.log('ERROR RESPONSE:', error?.error);

        console.log(
          'MERCADO PAGO ERROR:',
          error?.error?.mercadopago_error
        );

        this.isRedirectingToPayment = false;
        paymentWindow.close();

        this.cartMessage =
          error?.error?.mercadopago_error?.message
          || error?.error?.message
          || error?.message
          || 'No se pudo abrir Mercado Pago en este momento.';
      }
    });
  }

  get isAdmin(): boolean {
    return this.authService.isAdmin();
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  getImageUrl(item: CartItem): string {
    if (!item.image_path) {
      return 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="220" height="180"><rect fill="%23ececec" width="220" height="180"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="13">Sin imagen</text></svg>';
    }

    return item.image_path;
  }

  onImageError(event: any): void {
    event.target.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="220" height="180"><rect fill="%23ececec" width="220" height="180"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="13">No disponible</text></svg>';
  }

  getRemainingTime(item: CartItem): string {
    const remaining = Math.max(0, item.remaining_seconds || 0);
    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
  }

  getTotalItems(): number {
    return this.cartItems.reduce((total, item) => total + item.cantidad, 0);
  }

  trackByProductId(_: number, item: CartItem): number {
    return item.id;
  }

  isItemLoading(id: number): boolean {
    return this.loadingItems.has(id);
  }
}
