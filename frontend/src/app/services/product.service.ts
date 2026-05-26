import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class ProductService {
  private API_URL = `${environment.apiBaseUrl}/productos`;
  private CATEGORIES_URL = `${environment.apiBaseUrl}/categorias`;

  constructor(private http: HttpClient) {}

  getProducts(): Observable<any> {
    return this.http.get<any>(this.API_URL);
  }

  getCategories(): Observable<any> {
    return this.http.get<any>(this.CATEGORIES_URL);
  }

  getProduct(id: number | string): Observable<any> {
    return this.http.get<any>(`${this.API_URL}/${id}`);
  }

  createProduct(productData: FormData): Observable<any> {
    return this.http.post<any>(this.API_URL, productData);
  }

  updateProduct(id: number | string, productData: any): Observable<any> {
    if (productData instanceof FormData) {
      if (!productData.has('_method')) {
        productData.append('_method', 'PUT');
      }

      return this.http.post<any>(`${this.API_URL}/${id}`, productData);
    }

    return this.http.put<any>(`${this.API_URL}/${id}`, productData);
  }

  deleteProduct(id: number | string): Observable<any> {
    return this.http.delete<any>(`${this.API_URL}/${id}`);
  }
}
