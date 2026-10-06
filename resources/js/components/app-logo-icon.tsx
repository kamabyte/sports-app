import type { SVGAttributes } from 'react';

/** Знак: кнопка «play» в кольце стадиона. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M20 2C30 2 38 10 38 20S30 38 20 38 2 30 2 20 10 2 20 2Zm0 4.5C12.5 6.5 6.5 12.5 6.5 20S12.5 33.5 20 33.5 33.5 27.5 33.5 20 27.5 6.5 20 6.5Zm-3.2 6.7 10 5.8a1.2 1.2 0 0 1 0 2.1l-10 5.8a1.2 1.2 0 0 1-1.8-1V14.2a1.2 1.2 0 0 1 1.8-1Z"
            />
        </svg>
    );
}
