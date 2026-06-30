import { Routes } from '@angular/router';
import { HomeComponent } from './components/home/home.js';
import { CreateProduct } from './components/create-product/create-product.js';
import { EditProduct } from './components/edit-product/edit-product.js';
import { LoginComponent } from './components/login/login.js';
import { RegisterComponent } from './components/register/register.js';
import { ForgotPasswordComponent } from './components/forgot-password/forgot-password.js';
import { ResetPasswordComponent } from './components/reset-password/reset-password.js';
import { ProductsComponent } from './components/products/products.js';
import { ChatbotComponent } from './components/chatbot/chatbot.js';
import { CartComponent } from './components/cart/cart.js';
import { OrdersComponent } from './components/orders/orders.js';
import { MyOrdersComponent } from './components/my-orders/my-orders.js';
import { ChatConversationsComponent } from './components/chat-conversations/chat-conversations.js';
import { UsersComponent } from './components/users/users.js';
import { UserProfileComponent } from './components/user-profile/user-profile.js';
import { RouletteComponent } from './components/roulette/roulette.js';
import { AuthGuard } from './guards/auth.guard.js';

export const routes: Routes = [
  { path: 'login', component: LoginComponent },
  { path: 'register', component: RegisterComponent },
  { path: 'forgot-password', component: ForgotPasswordComponent },
  { path: 'reset-password', component: ResetPasswordComponent },
  { path: 'home', component: HomeComponent, canActivate: [AuthGuard] },
  { path: 'productos', component: ProductsComponent, canActivate: [AuthGuard] },
  { path: 'nuevo-producto', component: CreateProduct, canActivate: [AuthGuard], data: { adminOnly: true } },
  { path: 'editar-producto/:id', component: EditProduct, canActivate: [AuthGuard], data: { adminOnly: true } },
  { path: 'chatbot', component: ChatbotComponent, canActivate: [AuthGuard], data: { userOnly: true } },
  { path: 'ruleta', component: RouletteComponent, canActivate: [AuthGuard], data: { userOnly: true } },
  { path: 'carrito', component: CartComponent, canActivate: [AuthGuard], data: { userOnly: true } },
  { path: 'mis-pedidos', component: MyOrdersComponent, canActivate: [AuthGuard], data: { userOnly: true } },
  { path: 'mis-conversaciones', component: ChatConversationsComponent, canActivate: [AuthGuard], data: { userOnly: true } },
  { path: 'mi-perfil', component: UserProfileComponent, canActivate: [AuthGuard], data: { userOnly: true } },
  { path: 'pedidos', component: OrdersComponent, canActivate: [AuthGuard], data: { adminOnly: true } },
  { path: 'usuarios', component: UsersComponent, canActivate: [AuthGuard], data: { adminOnly: true } },

  { path: '', redirectTo: '/login', pathMatch: 'full' },
  { path: '**', redirectTo: '/login', pathMatch: 'full' }
];
