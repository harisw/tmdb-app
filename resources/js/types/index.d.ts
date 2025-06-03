import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';
import { PageProps as InertiaPageProps } from '@inertiajs/inertia';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Language {
    code: string;
    name: string;
}

export interface Genre {
    slug: string;
    name: string;
}

export interface Company {
    slug: string;
    name: string;
}

export interface Keyword {
    slug: string;
    name: string;
}

export interface Movie {
    id: number;
    title: string;
    slug: string;
    rating: number;
    status: string;
    released_date: string;
    revenue: number;
    runtime: number;
    img_backdrop: string;
    imdb_id: string;
    overview: string;
    popularity: number;
    img_poster: string;
    tagline: string;
    language: Language;
}

export interface PageProps<T = {}> extends InertiaPageProps {
    auth: {
        user: User | null
    };
    flash: {
        success?: string;
        error?: string;
    };
}
