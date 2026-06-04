import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

interface FavoriteResponse {
  status: boolean;
  products: any[];
  product_ids: number[];
}

interface ToggleFavoriteResponse {
  status: boolean;
  is_favorite: boolean;
  message: string;
}

@Injectable({
  providedIn: 'root'
})
export class FavoriteService {
  private readonly API_URL = `${environment.apiBaseUrl}/favoritos`;

  constructor(private http: HttpClient) {}

  getFavorites(userId: number): Observable<FavoriteResponse> {
    return this.http.get<FavoriteResponse>(`${this.API_URL}/${userId}`);
  }

  toggleFavorite(userId: number, productId: number): Observable<ToggleFavoriteResponse> {
    return this.http.post<ToggleFavoriteResponse>(`${this.API_URL}/toggle`, {
      user_id: userId,
      producto_id: productId,
    });
  }
}
