import { ChangeDetectorRef, Component, OnDestroy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs/operators';
import { AuthService } from '../../services/auth.js';
import { ProductService } from '../../services/product.service.js';
import { CartService } from '../../services/cart.service.js';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './home.html',
  styleUrl: './home.css'
})
export class HomeComponent implements OnInit, OnDestroy {
  readonly categories = [
    'Todos',
    'Cuadernos y libretas',
    'Lapices y marcadores',
    'Cartulinas y hojas',
    'Herramientas escolares'
  ];

  userName: string | null = '';
  loggedUserId: number | null = null;
  productos: any[] = [];
  filteredProducts: any[] = [];
  hasProductos = false;
  searchText = '';
  cartCount = 0;
  selectedCategory = 'Todos';
  cartMessage = '';
  addingProductIds = new Set<number>();

  constructor(
    private authService: AuthService,
    private router: Router,
    private productService: ProductService,
    private cartService: CartService,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.userName = localStorage.getItem('user_name') || 'Aprendiz';

    const userIdValue = localStorage.getItem('user_id');
    this.loggedUserId = userIdValue ? Number(userIdValue) : null;

    if (!this.isAdmin) {
      this.cartService.syncCurrentUser();
      this.cartService.items$.subscribe(() => {
        this.cartCount = this.cartService.getCount();
        this.cdr.detectChanges();
      });
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

  applyFilters(): void {
    const term = this.searchText.toLowerCase();

    this.filteredProducts = this.productos.filter(p => {
      const matchesSearch = !this.searchText?.trim() || (
        (p.nombre && p.nombre.toLowerCase().includes(term)) ||
        (p.descripcion && p.descripcion.toLowerCase().includes(term))
      );

      if (this.selectedCategory === 'Todos') {
        return matchesSearch;
      }

      const productCategory = (p.categoria || '').toLowerCase();
      return matchesSearch && productCategory.includes(this.selectedCategory.toLowerCase());
    });
  }

  getImageUrl(product: any): string {
    if (!product || !product.image_path) {
      return 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect fill="%23f0f0f0" width="200" height="200"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="14">Sin imagen</text></svg>';
    }

    return product.image_path;
  }

  onImageError(event: any): void {
    console.error('Error cargando imagen:', event.target.src);
    event.target.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect fill="%23ff6b6b" width="200" height="200"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="12" fill="white">Error al cargar</text></svg>';
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  get isAdmin(): boolean {
    return this.authService.isAdmin();
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

  setCategory(category: string): void {
    this.selectedCategory = category;
    this.applyFilters();
  }
}
