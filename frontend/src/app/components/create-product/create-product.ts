import { ChangeDetectorRef, Component, OnInit } from "@angular/core";
import { CommonModule } from "@angular/common";
import { RouterModule } from "@angular/router";
import { Router } from "@angular/router";
import { FormsModule } from "@angular/forms";
import { AuthService } from "../../services/auth";
import { ProductService } from "../../services/product.service";

@Component({
  selector: "app-create-product",
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: "./create-product.html",
  styleUrls: ['./create-product.css'],
})
export class CreateProduct implements OnInit {
  categories = [
    "Cuadernos y libretas",
    "Lapices y marcadores",
    "Cartulinas y hojas",
    "Herramientas escolares",
  ];

  name = "";
  category = "";
  description = "";
  price: number | null = null;
  stock: number | null = null;
  selectedFile: File | null = null;
  selectedFiles: File[] = [];
  imagePreview: string | null = null;
  imagePreviews: string[] = [];
  readonly imageSlots = [0, 1, 2, 3, 4];
  pendingImageSlot = 0;
  errors: { type: string; message: string }[] = [];
  isLoading = false;
  showModal = false;
  modalType: "success" | "error" = "success";
  modalTitle = "";
  modalMessage = "";

  constructor(
    private productService: ProductService,
    private authService: AuthService,
    private router: Router,
    private cdr: ChangeDetectorRef
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
        // Conserva las categorias locales como respaldo.
      }
    });
  }

  clearErrors(): void {
    this.errors = [];
  }

  addError(type: string, message: string): void {
    this.errors.push({ type, message });
  }

  selectImageSlot(index: number, fileInput: HTMLInputElement): void {
    this.pendingImageSlot = index;
    fileInput.click();
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length) {
      const availableSlots = Math.max(0, 5 - this.pendingImageSlot);
      const files = Array.from(input.files).slice(0, availableSlots);
      this.clearErrors();

      const validTypes = ["image/png", "image/jpg", "image/jpeg"];

      const invalidFile = files.find(file => !validTypes.includes(file.type));
      if (invalidFile) {
        this.addError("warning", "Tipo de imagen invalido. Solo se permiten PNG, JPG o JPEG");
        input.value = "";
        return;
      }

      const maxSize = 5 * 1024 * 1024;
      const oversizedFile = files.find(file => file.size > maxSize);
      if (oversizedFile) {
        this.addError("warning", "La imagen no debe exceder 5MB. Tu archivo pesa: " + (oversizedFile.size / 1024 / 1024).toFixed(2) + "MB");
        input.value = "";
        return;
      }

      files.forEach((file, index) => {
        const targetIndex = this.pendingImageSlot + index;
        this.selectedFiles[targetIndex] = file;

        const reader = new FileReader();
        reader.onload = (e: ProgressEvent<FileReader>) => {
          if (e.target?.result) {
            this.imagePreviews[targetIndex] = e.target.result as string;
            this.imagePreview = this.imagePreviews[0] || null;
            this.cdr.detectChanges();
          }
        };
        reader.readAsDataURL(file);
      });

      this.selectedFile = this.selectedFiles.find(Boolean) || null;
      this.imagePreview = this.imagePreviews[0] || null;
      input.value = "";
      this.clearErrors();
      this.addError("success", files.length > 1 ? "Imagenes cargadas correctamente" : "Imagen cargada correctamente");
    }
  }

  removeImage(index: number): void {
    delete this.selectedFiles[index];
    delete this.imagePreviews[index];
    this.selectedFile = this.selectedFiles.find(Boolean) || null;
    this.imagePreview = this.imagePreviews[0] || null;
  }

  clearImage(): void {
    this.selectedFile = null;
    this.selectedFiles = [];
    this.imagePreview = null;
    this.imagePreviews = [];
  }

  validateForm(): boolean {
    this.clearErrors();
    let isValid = true;

    if (!this.name.trim()) {
      this.addError("danger", "El nombre del producto es requerido");
      isValid = false;
    }

    if (!this.category.trim()) {
      this.addError("danger", "La categoria es requerida");
      isValid = false;
    }

    if (!this.description.trim()) {
      this.addError("danger", "La descripcion es requerida");
      isValid = false;
    }

    if (this.price === null || this.price === undefined) {
      this.addError("danger", "El precio es requerido");
      isValid = false;
    } else if (this.price <= 0) {
      this.addError("danger", "El precio debe ser mayor a 0");
      isValid = false;
    }

    if (this.stock === null || this.stock === undefined) {
      this.addError("danger", "El stock es requerido");
      isValid = false;
    } else if (this.stock < 0) {
      this.addError("danger", "El stock no puede ser negativo");
      isValid = false;
    }

    if (this.selectedFiles.filter(Boolean).length === 0) {
      this.addError("danger", "Debes seleccionar al menos una imagen para el producto");
      isValid = false;
    }

    return isValid;
  }

  saveProduct(): void {
    if (!this.validateForm()) {
      return;
    }

    this.isLoading = true;
    this.clearErrors();

    const userId = this.authService.getUserId();
    const formData = new FormData();
    formData.append("nombre", this.name);
    formData.append("categoria", this.category);
    formData.append("descripcion", this.description);
    formData.append("precio", String(this.price));
    formData.append("stock", String(this.stock));
    this.imageSlots.forEach((index) => {
      const file = this.selectedFiles[index];

      if (file) {
        formData.append("images[]", file, file.name);
      }
    });

    if (userId) {
      formData.append("user_id", userId);
    }

    this.productService.createProduct(formData).subscribe(
      () => {
        this.isLoading = false;
        this.router.navigate(["/productos"], {
          queryParams: { productCreated: "true", refresh: Date.now() }
        });
      },
      (error) => {
        this.isLoading = false;
        console.error("Error creando producto", error);
        this.openModal("error", "Error", "Error al crear el producto");

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
          this.addError("danger", "No se pudo conectar al servidor. Verifica que este activo.");
        } else if (error?.status === 500) {
          this.addError("danger", "Error del servidor. Intenta de nuevo mas tarde.");
        } else if (error?.error?.message) {
          this.addError("danger", error.error.message);
        } else {
          this.addError("danger", "Error al crear el producto. Intenta de nuevo.");
        }
      }
    );
  }

  goHome(): void {
    this.router.navigate(["/productos"], {
      queryParams: { refresh: Date.now() }
    });
  }

  openModal(type: "success" | "error", title: string, message: string): void {
    this.showModal = true;
    this.modalType = type;
    this.modalTitle = title;
    this.modalMessage = message;
  }

  closeModal(): void {
    const shouldReturnHome = this.modalType === "success";
    this.showModal = false;

    if (shouldReturnHome) {
      this.router.navigate(["/productos"], {
        queryParams: { refresh: Date.now() }
      });
    }
  }

  cancel(): void {
    this.router.navigate(["/productos"], {
      queryParams: { refresh: Date.now() }
    });
  }
}
