import useSwr from 'swr';

export default function canvasUseSwr(key: any, fetcher: any, options: any) {
  function dataPane(useSWRNext: any) {
    return (key: any, fetcher: any, config: any) => {
      const swr = useSWRNext(key, fetcher, config);
      const id = decodeURIComponent(JSON.stringify(key));

      if (swr.data !== undefined && !swr.isLoading) {
        window.parent.postMessage({
          type: '_canvas_useswr_data_fetch',
          id,
          data: swr.data,
        });
      }

      if (swr.error) {
        window.parent.postMessage({
          type: '_canvas_useswr_error',
          id,
          data: swr.error,
          error: true,
        });
      }

      return swr;
    };
  }

  let use = [];
  if (
    options !== undefined &&
    options.use !== undefined &&
    Array.isArray(options.use)
  ) {
    use = options.use;
  }
  use.push(dataPane);

  const optionsOverride = {
    ...options,
    use,
  };

  return useSwr(key, fetcher, optionsOverride);
}
