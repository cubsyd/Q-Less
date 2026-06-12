import { Component, ChangeDetectorRef, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../services/auth.js';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-user-profile',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './user-profile.html',
  styleUrls: ['./user-profile.css']
})
export class UserProfileComponent implements OnInit {
  private readonly maxPhotoSize = 4 * 1024 * 1024;
  user: any = { name: '', email: '', profile_photo_url: null };
  passwordData = {
    password: '',
    password_confirmation: ''
  };
  isLoading = false;
  isSaving = false;
  isDeleting = false;
  showDeleteConfirm = false;
  message = '';
  selectedPhoto: File | null = null;
  previewUrl: string | null = null;
  navPhotoUrl: string | null = null;
  private readonly usersUrl = `${environment.apiBaseUrl}/users`;

  constructor(
    private http: HttpClient,
    private authService: AuthService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    const userId = Number(this.authService.getUserId());
    if (!userId) {
      this.router.navigate(['/login']);
      return;
    }
    this.navPhotoUrl = this.authService.getUserPhotoUrl();
    this.loadUser(userId);
  }

  loadUser(id: number): void {
    this.isLoading = true;
    this.http.get<any>(`${this.usersUrl}/${id}`).subscribe({
      next: (res) => {
        this.user = res.user || this.user;
        this.previewUrl = this.user.profile_photo_url || null;
        this.navPhotoUrl = this.previewUrl;
        this.authService.saveUserProfile(this.user);
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error cargando perfil', err);
        this.message = 'No se pudo cargar el perfil.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  onFileChange(event: any): void {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    if (file.size > this.maxPhotoSize) {
      this.selectedPhoto = null;
      this.message = 'La foto no puede superar 4 MB.';
      event.target.value = '';
      this.cdr.detectChanges();
      return;
    }

    this.selectedPhoto = file;
    this.previewUrl = URL.createObjectURL(file);
    this.message = 'Foto seleccionada. Guarda los cambios para actualizar tu usuario.';
    this.cdr.detectChanges();
  }

  getInitials(): string {
    const name = String(this.user?.name || this.user?.email || 'U').trim();
    return name
      .split(/\s+/)
      .slice(0, 2)
      .map(part => part.charAt(0).toUpperCase())
      .join('') || 'U';
  }

  save(): void {
    const userId = Number(this.authService.getUserId());
    if (!userId || this.isSaving) {
      return;
    }

    const form = new FormData();
    form.append('_method', 'PATCH');

    if (this.user.name?.trim()) {
      form.append('name', this.user.name.trim());
    }

    if (this.user.email?.trim()) {
      form.append('email', this.user.email.trim());
    }

    if (this.passwordData.password || this.passwordData.password_confirmation) {
      form.append('password', this.passwordData.password);
      form.append('password_confirmation', this.passwordData.password_confirmation);
    }

    if (this.selectedPhoto) {
      form.append('photo', this.selectedPhoto);
    }

    this.isSaving = true;
    this.message = 'Guardando cambios...';

    this.http.post<any>(`${this.usersUrl}/${userId}`, form).subscribe({
      next: (res) => {
        this.user = res.user || this.user;
        this.message = res.message || 'Perfil actualizado';
        this.authService.saveUserProfile(this.user);
        this.passwordData = { password: '', password_confirmation: '' };
        this.selectedPhoto = null;
        this.previewUrl = this.user.profile_photo_url || this.previewUrl;
        this.navPhotoUrl = this.previewUrl;
        this.isSaving = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error actualizando perfil', err);
        this.message = this.getErrorMessage(err);
        this.isSaving = false;
        this.cdr.detectChanges();
      }
    });
  }

  openDeleteConfirm(): void {
    this.showDeleteConfirm = true;
  }

  closeDeleteConfirm(): void {
    if (!this.isDeleting) {
      this.showDeleteConfirm = false;
    }
  }

  deleteProfile(): void {
    const userId = Number(this.authService.getUserId());
    if (!userId || this.isDeleting) {
      return;
    }

    this.isDeleting = true;
    this.message = 'Eliminando cuenta...';

    this.http.delete<any>(`${this.usersUrl}/${userId}`).subscribe({
      next: () => {
        this.authService.clearSession();
        this.router.navigate(['/login']);
      },
      error: (err) => {
        console.error('Error eliminando perfil', err);
        this.message = err?.error?.message || 'No se pudo eliminar tu cuenta.';
        this.isDeleting = false;
        this.showDeleteConfirm = false;
        this.cdr.detectChanges();
      }
    });
  }

  private getErrorMessage(error: any): string {
    const serverErrors = error?.error?.errors;
    if (serverErrors) {
      const firstError = Object.values(serverErrors).flat().find(Boolean);
      if (firstError) {
        return String(firstError);
      }
    }

    return error?.error?.message || 'No se pudo actualizar el perfil.';
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => this.router.navigate(['/login']),
      error: () => { this.authService.clearSession(); this.router.navigate(['/login']); }
    });
  }

  getRoleLabel(): string {
    if (this.user?.rol === 'admin') {
      return 'Administrador';
    }

    if (this.user?.rol === 'instructor') {
      return 'Instructor';
    }

    return 'Aprendiz';
  }
}
