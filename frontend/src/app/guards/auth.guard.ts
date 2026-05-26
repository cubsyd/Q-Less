import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivate, Router } from '@angular/router';
import { AuthService } from '../services/auth';

@Injectable({
  providedIn: 'root'
})
export class AuthGuard implements CanActivate {
  constructor(
    private authService: AuthService,
    private router: Router
  ) {}

  canActivate(route: ActivatedRouteSnapshot): boolean {
    const token = this.authService.getToken();
    
    if (!token) {
      this.router.navigate(['/login']);
      return false;
    }

    if (route.data?.['adminOnly'] && !this.authService.isAdmin()) {
      this.router.navigate(['/home']);
      return false;
    }

    if (route.data?.['userOnly'] && this.authService.isAdmin()) {
      this.router.navigate(['/productos']);
      return false;
    }

    return true;
  }
}
