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
  styleUrls: ['./cart.css']
})
export class CartComponent implements OnInit, OnDestroy {
  cartItems: CartItem[] = [];
  total = 0;
  cartMessage = '';
  isRedirectingToPayment = false;
  loadingItems = new Set<number>();
  selectedImageIndexes: Record<number, number> = {};
  private countdownIntervalId: number | null = null;
  private cartRefreshIntervalId: number | null = null;

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
        this.cartMessage = 'Pago aprobado en Mercado Pago. Tu pedido ya fue creado.';
        this.markPaymentApproved(params.get('external_reference'));
      } else if (paymentStatus === 'failure') {
        this.cartMessage = 'El pago no fue aprobado por Mercado Pago.';
      } else if (paymentStatus === 'pending') {
        this.cartMessage = 'El pago quedo pendiente en Mercado Pago.';
      }
    });

    this.cartService.syncCurrentUser();
    this.cartService.items$.subscribe(items => {
      this.cartItems = items.map(item => ({ ...item }));
      this.total = this.cartService.getTotal();
      this.cdr.detectChanges();
    });

    this.countdownIntervalId = window.setInterval(() => {
      this.updateDisplayedCountdowns();
    }, 1000);

    this.cartRefreshIntervalId = window.setInterval(() => {
      this.cartService.syncCurrentUser();
    }, 5000);
  }

  ngOnDestroy(): void {
    if (this.countdownIntervalId !== null) {
      window.clearInterval(this.countdownIntervalId);
    }

    if (this.cartRefreshIntervalId !== null) {
      window.clearInterval(this.cartRefreshIntervalId);
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

  updateQuantity(item: any, event: Event): void {
    const input = event.target as HTMLInputElement;
    let nuevaCantidad = parseInt(input.value, 10);

    if (isNaN(nuevaCantidad)) {
      nuevaCantidad = item.cantidad;
    }

    if (nuevaCantidad < 1) {
      nuevaCantidad = 1;
    }

    const maximo = item.cantidad + (item.stock_available ?? 0);

    if (nuevaCantidad > maximo) {
      nuevaCantidad = maximo;
    }

    input.value = nuevaCantidad.toString();

    if (nuevaCantidad === item.cantidad) {
      return;
    }

    this.cartMessage = 'Actualizando cantidad...';
    this.loadingItems.add(item.id);

    this.cartService.setItemQuantity(item.id, nuevaCantidad).subscribe({
      next: () => {
        this.total = this.cartService.getTotal();
        this.cartMessage = 'Cantidad actualizada.';
        this.loadingItems.delete(item.id);
      },
      error: (error) => {
        console.error('Error actualizando cantidad', error);
        this.cartMessage = error?.error?.errors?.cantidad?.[0]
          || error?.error?.message
          || 'No se pudo actualizar la cantidad del producto.';
        input.value = item.cantidad.toString();
        this.loadingItems.delete(item.id);
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

    if (paymentWindow) {
      paymentWindow.document.write('Abriendo Mercado Pago...');
    }

    this.cartService.createPaymentPreference().subscribe({

      next: (response: any) => {

        const paymentUrl =
          response.init_point ||
          response.sandbox_init_point;

        if (!paymentUrl) {

          console.error('Mercado Pago no devolvio URL');

          this.isRedirectingToPayment = false;
          paymentWindow?.close();

          this.cartMessage =
            'Mercado Pago no devolvio una URL de pago valida.';

          return;
        }

        const orderNumber = response.order_number || response.order?.order_number;
        const emailText = response.email_sent
          ? 'Tambien enviamos el correo a la cuenta asociada.'
          : 'El pedido fue creado; revisa la configuracion SMTP si no llega el correo.';

        this.cartMessage = orderNumber
          ? `Pedido creado correctamente con el numero de pedido #${orderNumber}. ${emailText}`
          : `Pedido creado correctamente. ${emailText}`;

        window.alert(this.cartMessage);
        this.cartService.syncCurrentUser();

        if (paymentWindow) {
          paymentWindow.location.href = paymentUrl;
        } else {
          window.location.href = paymentUrl;
        }

        this.isRedirectingToPayment = false;
      },

      error: (error) => {

        console.error('ERROR COMPLETO:', error);

        console.log('ERROR RESPONSE:', error?.error);

        console.log(
          'MERCADO PAGO ERROR:',
          error?.error?.mercadopago_error
        );

        this.isRedirectingToPayment = false;
        paymentWindow?.close();

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

  private markPaymentApproved(externalReference: string | null): void {
    const userId = this.authService.getUserId();

    if (!userId || !externalReference) {
      return;
    }

    this.cartService.createOrder(Number(userId), {
      payment_provider: 'mercadopago',
      payment_reference: externalReference,
      payment_status: 'approved',
    }).subscribe({
      next: (orderResponse: any) => {
        const orderNumber = orderResponse?.order?.order_number;

        if (orderNumber) {
          this.cartMessage = `Pago aprobado en Mercado Pago. Tu pedido #${orderNumber} ya fue creado.`;
        }

        this.cartService.syncCurrentUser();
        this.router.navigate(['/carrito'], { replaceUrl: true });
      },
      error: (error: any) => {
        console.error('ERROR ACTUALIZANDO PAGO:', error);
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

  getImageUrl(item: CartItem): string {
    const images = this.getItemImages(item);

    if (images.length === 0) {
      return 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="220" height="180"><rect fill="%23ececec" width="220" height="180"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="13">Sin imagen</text></svg>';
    }

    const selectedIndex = this.getSelectedImageIndex(item);

    return images[selectedIndex] || images[0];
  }

  getItemImages(item: CartItem): string[] {
    const images = Array.isArray(item.product_images)
      ? item.product_images.filter(Boolean)
      : [];

    if (images.length === 0 && item.image_path) {
      images.push(item.image_path);
    }

    return images;
  }

  hasMultipleImages(item: CartItem): boolean {
    return this.getItemImages(item).length > 1;
  }

  nextImage(item: CartItem, event: Event): void {
    event.stopPropagation();
    const images = this.getItemImages(item);

    if (images.length < 2) {
      return;
    }

    const currentIndex = this.getSelectedImageIndex(item);
    this.selectedImageIndexes[item.id] = (currentIndex + 1) % images.length;
  }

  previousImage(item: CartItem, event: Event): void {
    event.stopPropagation();
    const images = this.getItemImages(item);

    if (images.length < 2) {
      return;
    }

    const currentIndex = this.getSelectedImageIndex(item);
    this.selectedImageIndexes[item.id] = (currentIndex - 1 + images.length) % images.length;
  }

  getImagePosition(item: CartItem): string {
    const images = this.getItemImages(item);
    const selectedIndex = this.getSelectedImageIndex(item);

    return `${selectedIndex + 1}/${images.length}`;
  }

  private getSelectedImageIndex(item: CartItem): number {
    const images = this.getItemImages(item);
    const selectedIndex = this.selectedImageIndexes[item.id] || 0;

    if (images.length === 0 || selectedIndex < images.length) {
      return selectedIndex;
    }

    this.selectedImageIndexes[item.id] = 0;
    return 0;
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

  private updateDisplayedCountdowns(): void {
    if (this.cartItems.length === 0) {
      return;
    }

    this.cartItems = this.cartItems.map(item => {
      const remaining = this.calculateRemainingSeconds(item);

      return {
        ...item,
        remaining_seconds: remaining,
      };
    });

    this.cdr.detectChanges();
  }

  private calculateRemainingSeconds(item: CartItem): number {
    if (item.expires_at) {
      const expiresAt = new Date(item.expires_at).getTime();

      if (!Number.isNaN(expiresAt)) {
        return Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
      }
    }

    return Math.max(0, (item.remaining_seconds || 0) - 1);
  }
}
