import NextImage from 'next-image-standalone';

import type { ImageLoaderParams, ImageProps } from 'next-image-standalone';

export default function Image(
  props: Omit<ImageProps, 'loader'> & {
    // `next-image-standalone` expects a loader function, but we make that
    // optional as long as an `src` prop is provided with a `alternateWidths`
    // query string parameter, in which case we'll provide a default loader.
    loader?: (params: ImageLoaderParams) => string;
  },
) {
  const { src, loader } = props;

  if (!loader) {
    //
    const defaultLoader = ({ width, imageProps }: ImageLoaderParams) => {
      try {
        // Parse the `alternateWidths` query string parameter from `src`.
        // Example `src` value:
        // /sites/default/files/2025-07/maple-street.jpg?alternateWidths=/sites/default/files/styles/canvas_parametrized_width--{width}/public/2025-07/maple-street.jpg.webp?itok=…
        // A base URL is passed to the `URL` constructor to handle the relative
        // path. This is only so we can easily parse the
        let result = new URL(src, 'https://example.com').searchParams.get(
          'alternateWidths',
        );
        if (!result) {
          throw new Error(
            'Responsive image generation requires an `alternateWidths` query parameter in the image URL.',
          );
        }
        result = result.replace('{width}', width.toString());

        if (result.includes('{height}')) {
          // This loader only needs to deal with the height when the example
          // image is loaded from https://placehold.co, in which case adding a
          // height in the URL is required. As a workaround, the code editor adds
          // "{height}" as part of the `alternateWidths` query string parameter,
          // so we can do the replacement here.
          // `next/image` only passes the width to the loader, but
          // `next-standalone-image` also exposes an `imageProps` parameter,
          // which gives us access to the intrinsic image dimensions.
          // Based on those we can also calculate the appropriate height for the
          // resized placeholder image.
          const { width: intrinsicWidth, height: intrinsicHeight } = imageProps;
          const height = Math.round(width / (intrinsicWidth / intrinsicHeight));
          result = result.replace('{height}', height.toString());
        }

        return result;
      } catch (error) {
        console.error(
          'Responsive image generation failed. To fix this:\n' +
            '1. Provide a custom `loader` function, or\n' +
            '2. Ensure your image `src` includes an `alternateWidths` query parameter\n' +
            '   Example: ?alternateWidths=/path/to/responsive/{width}/image.jpg',
          { src, error },
        );
        // Fallback to original `src` if parsing fails.
        return src;
      }
    };
    return (
      <NextImage
        {...props}
        loader={defaultLoader}
        sizes={props.sizes || 'auto 100vw'}
      />
    );
  }
  return (
    <NextImage {...props} loader={loader} sizes={props.sizes || 'auto 100vw'} />
  );
}
