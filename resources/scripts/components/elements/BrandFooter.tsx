import React from 'react';
import { useStoreState } from 'easy-peasy';
import classNames from 'classnames';
import { ApplicationStore } from '@/state';

// Footer brand (BRAND_FOOTER dan BRAND_URL di .env). Tanpa BRAND_FOOTER: "© <tahun> <nama panel>".
export default ({ className }: { className?: string }) => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data?.name);
    const brand = useStoreState((state: ApplicationStore) => state.settings.data?.brand);
    const text = brand?.footer || `© ${new Date().getFullYear()} ${name ?? ''}`;

    return (
        <p className={classNames('text-center text-neutral-500 text-xs', className)}>
            {brand?.url ? (
                <a href={brand.url} className={'no-underline text-neutral-500 hover:text-neutral-300'}>
                    {text}
                </a>
            ) : (
                text
            )}
        </p>
    );
};
