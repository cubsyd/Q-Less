import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { AuthService } from '../../services/auth.js';
import { ProductService } from '../../services/product.service.js';
import { CartService } from '../../services/cart.service.js';
import { FavoriteService } from '../../services/favorite.service';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './home.html',
  styleUrls: ['./home.css']
})
export class HomeComponent implements OnInit, OnDestroy {
  readonly mobileAppDownloadUrl = 'https://github.com/AnderFelipeOrtiz2004/Q-Less/releases/download/v0.2.7-mobile/Q-LESS-v0.2.7-release.apk';

  readonly categories = [
    'Todos',
    'Favoritos',
    'Cuadernos y libretas',
    'Lapices y marcadores',
    'Cartulinas y hojas',
    'Herramientas escolares'
  ];

  userName: string | null = '';
  userPhotoUrl: string | null = null;
  loggedUserId: number | null = null;
  productos: any[] = [];
  filteredProducts: any[] = [];
  hasProductos = false;
  searchText = '';
  cartCount = 0;
  selectedCategory = 'Todos';
  cartMessage = '';
  addingProductIds = new Set<number>();
  favoriteProductIds = new Set<number>();
  updatingFavoriteIds = new Set<number>();

  constructor(
    private authService: AuthService,
    private router: Router,
    private productService: ProductService,
    private cartService: CartService,
    private favoriteService: FavoriteService,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    this.userName = this.authService.getUserName('Aprendiz');

    const userIdValue = this.authService.getUserId();
    this.loggedUserId = userIdValue ? Number(userIdValue) : null;

    if (!this.isAdmin) {
      this.loadNavProfile();
      this.cartService.syncCurrentUser();
      this.cartService.items$.subscribe(() => {
        this.cartCount = this.cartService.getCount();
        this.cdr.detectChanges();
      });

      this.loadFavorites();
    }

    this.loadProducts();

    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event: any) => {
      if (event.url === '/home' || event.urlAfterRedirects === '/home') {
        this.loadProducts();
      }
    });

  }

  ngOnDestroy(): void {
  }

  loadProducts(): void {
    this.productService.getProducts().subscribe(
      (response: any) => {
        const data = Array.isArray(response) ? response : response?.data || [];
        this.productos = data;
        this.hasProductos = this.productos.length > 0;
        this.applyFilters();
        this.cdr.detectChanges();
        console.log('Productos cargados:', this.productos);
      },
      error => {
        console.error('Error cargando productos', error);
        this.hasProductos = this.productos.length > 0;
        this.applyFilters();
        this.cdr.detectChanges();
      }
    );
  }

  loadFavorites(): void {
    const userId = this.getCurrentUserId();

    if (!userId) {
      this.favoriteProductIds.clear();
      return;
    }

    this.favoriteService.getFavorites(userId).subscribe({
      next: (response) => {
        this.favoriteProductIds = new Set((response.product_ids || []).map(Number));
        this.applyFilters();
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error cargando favoritos', error);
      }
    });
  }

  applyFilters(): void {
    const term = this.searchText.toLowerCase();

    this.filteredProducts = this.productos.filter(p => {
      const matchesSearch = !this.searchText?.trim() || (
        (p.nombre && p.nombre.toLowerCase().includes(term)) ||
        (p.descripcion && p.descripcion.toLowerCase().includes(term))
      );

      if (this.selectedCategory === 'Favoritos') {
        return matchesSearch && this.favoriteProductIds.has(Number(p.id));
      }

      if (this.selectedCategory === 'Todos') {
        return matchesSearch;
      }

      const productCategory = (p.categoria || '').toLowerCase();
      return matchesSearch && productCategory.includes(this.selectedCategory.toLowerCase());
    });
  }

  getImageUrl(product: any): string {
    const imagePath = this.getProductImages(product)[0];

    if (!imagePath) {
      return 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect fill="%23f0f0f0" width="200" height="200"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="14">Sin imagen</text></svg>';
    }

    return this.normalizeImageUrl(imagePath);
  }

  getProductImages(product: any): string[] {
    if (!product) {
      return [];
    }

    const images: string[] = Array.isArray(product.product_images)
      ? product.product_images.filter(Boolean)
      : [];

    if (images.length === 0 && product.image_path) {
      images.push(product.image_path);
    }

    return images;
  }

  normalizeImageUrl(imagePath: string): string {
    if (!imagePath || imagePath.startsWith('data:') || imagePath.startsWith('blob:')) {
      return imagePath;
    }

    const backendBaseUrl = environment.apiBaseUrl.replace(/\/api\/?$/, '');

    if (imagePath.startsWith('/storage/')) {
      return `${backendBaseUrl}${imagePath}`;
    }

    if (imagePath.startsWith('storage/')) {
      return `${backendBaseUrl}/${imagePath}`;
    }

    try {
      const parsedUrl = new URL(imagePath);

      if (parsedUrl.hostname === 'localhost' || parsedUrl.hostname === '127.0.0.1') {
        return `${backendBaseUrl}${parsedUrl.pathname}`;
      }
    } catch {
      return imagePath;
    }

    return imagePath;
  }

  onImageError(event: any): void {
    const currentSrc = event.target?.src || '';
    const retried = event.target?.dataset?.retried === 'true';

    if (!retried && currentSrc.includes('/storage/productos/')) {
      event.target.dataset.retried = 'true';
      event.target.src = currentSrc.replace('/storage/productos/', '/storage/products/');
      return;
    }

    if (!retried && currentSrc.includes('/storage/products/')) {
      event.target.dataset.retried = 'true';
      event.target.src = currentSrc.replace('/storage/products/', '/storage/productos/');
      return;
    }

    console.error('Error cargando imagen:', currentSrc);
    event.target.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect fill="%23ff6b6b" width="200" height="200"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="12" fill="white">Error al cargar</text></svg>';
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

  get isAdmin(): boolean {
    return this.authService.isAdmin();
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

  isLowStock(product: any): boolean {
    const stock = Number(product?.stock);

    return Number.isFinite(stock) && stock > 0 && stock < 5;
  }

  addToCart(product: any): void {
    if (this.isAdmin) {
      return;
    }

    if (this.addingProductIds.has(product.id)) {
      return;
    }

    this.cartMessage = '';
    this.addingProductIds.add(product.id);
    this.cdr.detectChanges();

    this.cartService.addItem(product).subscribe({
      next: (response) => {
        this.cartCount = response?.count ?? this.cartService.getCount();
        this.productos = this.productos.map(p => {
          if (p.id !== product.id || typeof p.stock !== 'number' || p.stock <= 0) {
            return p;
          }

          return {
            ...p,
            stock: p.stock - 1,
          };
        });

        this.addingProductIds.delete(product.id);
        this.applyFilters();
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error agregando al carrito', error);
        this.addingProductIds.delete(product.id);
        this.cartMessage = error?.error?.errors?.producto_id?.[0]
          || error?.error?.errors?.carrito?.[0]
          || error?.error?.message
          || 'No se pudo agregar el producto al carrito.';
        this.cdr.detectChanges();
      }
    });
  }

  isInCart(productId: number): boolean {
    return this.cartService.hasItem(productId);
  }

  isAddingToCart(productId: number): boolean {
    return this.addingProductIds.has(productId);
  }

  isFavorite(productId: number): boolean {
    return this.favoriteProductIds.has(Number(productId));
  }

  isUpdatingFavorite(productId: number): boolean {
    return this.updatingFavoriteIds.has(Number(productId));
  }

  toggleFavorite(product: any): void {
    const userId = this.getCurrentUserId();

    if (!userId || this.isAdmin || this.updatingFavoriteIds.has(product.id)) {
      return;
    }

    this.updatingFavoriteIds.add(product.id);
    this.cartMessage = '';
    this.cdr.detectChanges();

    this.favoriteService.toggleFavorite(userId, product.id).subscribe({
      next: (response) => {
        if (response.is_favorite) {
          this.favoriteProductIds.add(Number(product.id));
        } else {
          this.favoriteProductIds.delete(Number(product.id));
        }

        this.cartMessage = response.message;
        this.updatingFavoriteIds.delete(product.id);
        this.applyFilters();
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error actualizando favorito', error);
        this.cartMessage = error?.error?.message || 'No se pudo actualizar favoritos.';
        this.updatingFavoriteIds.delete(product.id);
        this.cdr.detectChanges();
      }
    });
  }

  setCategory(category: string): void {
    this.selectedCategory = category;
    this.applyFilters();
  }

  private getCurrentUserId(): number | null {
    const userId = this.authService.getUserId();
    return userId ? Number(userId) : null;
  }
}
