import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.js';
import { ProductService } from '../../services/product.service.js';
import { CartService } from '../../services/cart.service.js';
import { FavoriteService } from '../../services/favorite.service';

@Component({
  selector: 'app-products',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './products.html',
  styleUrls: ['./products.css']
})
export class ProductsComponent implements OnInit, OnDestroy {
  userName: string | null = '';
  productos: any[] = [];
  filteredProducts: any[] = [];
  searchText = '';
  selectedCategory = 'Todos';
  cartCount = 0;
  cartMessage = '';
  addingProductIds = new Set<number>();
  favoriteProductIds = new Set<number>();
  updatingFavoriteIds = new Set<number>();
  selectedImageIndexes: Record<number, number> = {};
  private productsRefreshIntervalId: number | null = null;

  readonly categories = [
    'Todos',
    'Favoritos',
    'Cuadernos y libretas',
    'Lapices y marcadores',
    'Cartulinas y hojas',
    'Herramientas escolares'
  ];

  constructor(
    private authService: AuthService,
    private productService: ProductService,
    private router: Router,
    private route: ActivatedRoute,
    private cdr: ChangeDetectorRef,
    private cartService: CartService,
    private favoriteService: FavoriteService
  ) { }

  ngOnInit(): void {
    this.userName = localStorage.getItem('user_name') || 'Aprendiz';

    if (!this.isAdmin) {
      this.cartService.syncCurrentUser();
      this.cartService.items$.subscribe(() => {
        this.cartCount = this.cartService.getCount();
        this.cdr.detectChanges();
      });

      this.loadFavorites();
    }

    this.route.queryParamMap.subscribe(params => {
      const category = params.get('categoria');
      const productCreated = params.get('productCreated');

      if (productCreated === 'true') {
        this.cartMessage = 'Producto creado exitosamente.';
      }

      if (category && this.categories.includes(category)) {
        this.selectedCategory = category;
      } else {
        this.selectedCategory = 'Todos';
      }

      this.loadProducts();
    });

    this.productsRefreshIntervalId = window.setInterval(() => {
      this.loadProducts();
    }, 5000);
  }

  ngOnDestroy(): void {
    if (this.productsRefreshIntervalId !== null) {
      window.clearInterval(this.productsRefreshIntervalId);
    }
  }

  setCategory(category: string): void {
    this.selectedCategory = category;
    this.applyFilters();

    this.router.navigate(['/productos'], {
      queryParams: category === 'Todos' ? { refresh: Date.now() } : { categoria: category, refresh: Date.now() }
    });
  }

  loadProducts(): void {
    this.productService.getProducts().subscribe({
      next: (response: any) => {
        this.productos = Array.isArray(response) ? response : response?.data || [];
        this.applyFilters();
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error cargando productos', error);
      }
    });
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
    const term = this.searchText.trim().toLowerCase();

    this.filteredProducts = this.productos.filter((product) => {
      const matchesSearch = !term ||
        (product.nombre && product.nombre.toLowerCase().includes(term)) ||
        (product.descripcion && product.descripcion.toLowerCase().includes(term));

      if (this.selectedCategory === 'Favoritos') {
        return matchesSearch && this.favoriteProductIds.has(Number(product.id));
      }

      if (this.selectedCategory === 'Todos') {
        return matchesSearch;
      }

      const productCategory = (product.categoria || '').toLowerCase();
      return matchesSearch && productCategory.includes(this.selectedCategory.toLowerCase());
    });
  }

  getImageUrl(product: any): string {
    const images = this.getProductImages(product);

    if (images.length === 0) {
      return 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="220" height="180"><rect fill="%23f0f0f0" width="220" height="180"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="14">Sin imagen</text></svg>';
    }

    const selectedIndex = this.getSelectedImageIndex(product);

    return images[selectedIndex] || images[0];
  }

  getProductImages(product: any): string[] {
    if (!product) {
      return [];
    }

    const images = Array.isArray(product.product_images)
      ? product.product_images.filter(Boolean)
      : [];

    if (images.length === 0 && product.image_path) {
      images.push(product.image_path);
    }

    return images;
  }

  hasMultipleImages(product: any): boolean {
    return this.getProductImages(product).length > 1;
  }

  nextImage(product: any, event: Event): void {
    event.stopPropagation();
    const images = this.getProductImages(product);

    if (images.length < 2) {
      return;
    }

    const productId = Number(product.id);
    const currentIndex = this.getSelectedImageIndex(product);
    this.selectedImageIndexes[productId] = (currentIndex + 1) % images.length;
  }

  previousImage(product: any, event: Event): void {
    event.stopPropagation();
    const images = this.getProductImages(product);

    if (images.length < 2) {
      return;
    }

    const productId = Number(product.id);
    const currentIndex = this.getSelectedImageIndex(product);
    this.selectedImageIndexes[productId] = (currentIndex - 1 + images.length) % images.length;
  }

  getImagePosition(product: any): string {
    const images = this.getProductImages(product);
    const selectedIndex = this.getSelectedImageIndex(product);

    return `${selectedIndex + 1}/${images.length}`;
  }

  private getSelectedImageIndex(product: any): number {
    const images = this.getProductImages(product);
    const selectedIndex = this.selectedImageIndexes[Number(product?.id)] || 0;

    if (images.length === 0 || selectedIndex < images.length) {
      return selectedIndex;
    }

    this.selectedImageIndexes[Number(product.id)] = 0;
    return 0;
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

    event.target.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="220" height="180"><rect fill="%23dedede" width="220" height="180"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="13">No disponible</text></svg>';
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

  isLowStock(product: any): boolean {
    const stock = Number(product?.stock);

    return Number.isFinite(stock) && stock > 0 && stock < 5;
  }

  isOutOfStock(product: any): boolean {
    const stock = Number(product?.stock);

    return !Number.isFinite(stock) || stock <= 0;
  }

  addToCart(product: any): void {
    if (this.isAdmin) {
      return;
    }

    if (this.isOutOfStock(product)) {
      this.cartMessage = 'Este producto esta agotado. No se puede agregar al carrito hasta que se actualice el stock.';
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

  private getCurrentUserId(): number | null {
    const userId = localStorage.getItem('user_id');
    return userId ? Number(userId) : null;
  }
}
