import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import {type BreadcrumbItem, Movie, PageProps} from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
];

export default function Index({ movies }: PageProps<{movies: Movie[]}>) {
    const firstMovie = movies[0];
    console.log(firstMovie);
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={window.appName} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div
                    className="absolute inset-0 bg-[url('/path/to/your-image.jpg')] bg-cover bg-center bg-no-repeat z-0"
                ></div>

                <div className="absolute inset-0 bg-black/50 z-10"></div>

                {/*<div className="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border md:min-h-min">*/}
                {/*    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />*/}
                {/*</div>*/}
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                    <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
