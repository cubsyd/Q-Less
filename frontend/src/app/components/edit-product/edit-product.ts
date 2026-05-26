import { Component, OnInit } from "@angular/core";
import { CommonModule } from "@angular/common";
import { ActivatedRoute, Router, RouterModule } from "@angular/router";
import { FormsModule } from "@angular/forms";
import { AuthService } from "../../services/auth";
import { ProductService } from "../../services/product.service";

@Component({
  selector: "app-edit-product",
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: "./edit-product.html",
  styleUrl: "./edit-product.css",
})
export class EditProduct implements OnInit {
  categories = [
    "Cuadernos y libretas",
    "Lapices y marcadores",
    "Cartulinas y hojas",
    "Herramientas escolares",
  ];

  productId: string | null = null;
  product: any = {
    id: null,
    nombre: "",
    categoria: "",
    categoria_id: null,
    descripcion: "",
    precio: null,
    stock: null,
    image_path: ""
  };
  selectedFile: File | null = null;
  previewUrl: string | null = null;
  isLoading = false;
  errors: { type: string; message: string }[] = [];

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private authService: AuthService,
    private productService: ProductService
  ) {}

  ngOnInit(): void {
    if (!this.authService.isAdmin()) {
      this.router.navigate(["/home"]);
      return;
    }

    this.productService.getCategories().subscribe({
      next: (response: any) => {
        const categories = Array.isArray(response) ? response : response?.data || [];
        if (categories.length > 0) {
          this.categories = categories.map((category: any) => category.nombre);
        }
      },
      error: () => {
        // Mantiene las categorias locales como respaldo.
      }
    });

    this.productId = this.route.snapshot.paramMap.get("id");
    const navigationProduct = history.state?.product;

    if (navigationProduct && this.productId && String(navigationProduct.id) === this.productId) {
      this.product = {
        id: navigationProduct.id,
        nombre: navigationProduct.nombre || "",
        categoria: navigationProduct.categoria || "",
        categoria_id: navigationProduct.categoria_id || null,
        descripcion: navigationProduct.descripcion || "",
        precio: navigationProduct.precio,
        stock: navigationProduct.stock,
        image_path: navigationProduct.image_path || ""
      };
    }

    if (this.productId) {
      this.loadProduct();
    }
  }

  clearErrors(): void {
    this.errors = [];
  }

  addError(type: string, message: string): void {
    this.errors.push({ type, message });
  }

  loadProduct(): void {
    this.productService.getProduct(this.productId!).subscribe(
      (found: any) => {
        this.product = {
          id: found.id,
          nombre: found.nombre,
          categoria: found.categoria || found.categoriaRelacion?.nombre || "",
          categoria_id: found.categoria_id || found.categoriaRelacion?.id || null,
          descripcion: found.descripcion || "",
          precio: found.precio,
          stock: found.stock,
          image_path: found.image_path || ""
        };
      },
      (error) => {
        console.error("Error cargando producto", error);
        this.addError("danger", "Error al cargar el producto. Intenta de nuevo.");
      }
    );
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length) {
      const file = input.files[0];
      this.clearErrors();

      const validTypes = ["image/png", "image/jpg", "image/jpeg"];
      if (!validTypes.includes(file.type)) {
        this.addError("warning", "Tipo de imagen invalido. Solo PNG, JPG o JPEG");
        return;
      }

      const maxSize = 5 * 1024 * 1024;
      if (file.size > maxSize) {
        const sizeMB = (file.size / 1024 / 1024).toFixed(2);
        this.addError("warning", `La imagen pesa ${sizeMB}MB. Maximo 5MB`);
        return;
      }

      this.selectedFile = file;

      const reader = new FileReader();
      reader.onload = (e: ProgressEvent<FileReader>) => {
        if (e.target?.result) {
          this.previewUrl = e.target.result as string;
        }
      };
      reader.readAsDataURL(file);
    }
  }

  clearSelectedImage(): void {
    this.selectedFile = null;
    this.previewUrl = null;
  }

  validateForm(): boolean {
    this.clearErrors();
    let isValid = true;

    if (!this.product.nombre?.trim()) {
      this.addError("danger", "El nombre del producto es requerido");
      isValid = false;
    }

    if (!this.product.categoria?.trim()) {
      this.addError("danger", "La categoria es requerida");
      isValid = false;
    }

    if (!this.product.descripcion?.trim()) {
      this.addError("danger", "La descripcion es requerida");
      isValid = false;
    }

    if (this.product.precio === null || this.product.precio === undefined) {
      this.addError("danger", "El precio es requerido");
      isValid = false;
    } else if (this.product.precio <= 0) {
      this.addError("danger", "El precio debe ser mayor a 0");
      isValid = false;
    }

    if (this.product.stock === null || this.product.stock === undefined) {
      this.addError("danger", "El stock es requerido");
      isValid = false;
    } else if (this.product.stock < 0) {
      this.addError("danger", "El stock no puede ser negativo");
      isValid = false;
    }

    return isValid;
  }

  updateProduct(): void {
    if (!this.validateForm() || !this.productId) {
      return;
    }

    this.isLoading = true;

    const formData = new FormData();
    formData.append("nombre", this.product.nombre);
    formData.append("categoria", this.product.categoria);
    formData.append("descripcion", this.product.descripcion);
    formData.append("precio", String(this.product.precio));
    formData.append("stock", String(this.product.stock));

    if (this.product.categoria_id) {
      formData.append("categoria_id", String(this.product.categoria_id));
    }

    if (this.selectedFile) {
      formData.append("image", this.selectedFile, this.selectedFile.name);
    }

    this.productService.updateProduct(this.productId, formData).subscribe(
      () => {
        this.isLoading = false;
        this.router.navigate(["/productos"], {
          queryParams: { refresh: Date.now() }
        });
      },
      (error) => {
        this.isLoading = false;
        console.error("Error actualizando producto", error);

        if (error?.status === 422) {
          if (error?.error?.errors?.nombre) {
            this.addError("danger", "Ya existe un producto con este nombre");
          } else if (error?.error?.errors?.precio) {
            this.addError("danger", "El formato del precio no es valido");
          } else if (error?.error?.errors?.categoria) {
            this.addError("danger", "Debes seleccionar una categoria valida");
          } else if (error?.error?.errors?.image) {
            this.addError("danger", "Hubo un error al procesar la imagen");
          } else if (error?.error?.message) {
            this.addError("danger", error.error.message);
          } else {
            this.addError("danger", "Datos invalidos. Revisa los campos");
          }
        } else if (error?.status === 0) {
          this.addError("danger", "No se pudo conectar al servidor");
        } else if (error?.status === 500) {
          this.addError("danger", "Error del servidor. Intenta mas tarde");
        } else if (error?.error?.message) {
          this.addError("danger", error.error.message);
        } else {
          this.addError("danger", "Error al actualizar el producto");
        }
      }
    );
  }

  deleteProduct(): void {
    if (!this.productId || !confirm("Estas seguro de que quieres eliminar este producto?")) {
      return;
    }

    this.isLoading = true;
    this.productService.deleteProduct(this.productId).subscribe(
      () => {
        this.isLoading = false;
        this.router.navigate(["/productos"], {
          queryParams: { refresh: Date.now() }
        });
      },
      (error) => {
        this.isLoading = false;
        console.error("Error eliminando producto", error);

        if (error?.status === 0) {
          this.addError("danger", "No se pudo conectar al servidor");
        } else if (error?.status === 500) {
          this.addError("danger", "Error del servidor al eliminar");
        } else if (error?.error?.message) {
          this.addError("danger", error.error.message);
        } else {
          this.addError("danger", "Error al eliminar el producto");
        }
      }
    );
  }

  goHome(): void {
    this.router.navigate(["/productos"], {
      queryParams: { refresh: Date.now() }
    });
  }

  cancel(): void {
    this.router.navigate(["/productos"], {
      queryParams: { refresh: Date.now() }
    });
  }
}
