import { DrupalJsonApiParams } from 'drupal-jsonapi-params';
import useSWR from 'swr';
import { JsonApiClient } from '@drupal-api-client/json-api-client';

import { getNodePath } from '@/lib/jsonapi-utils';

const client = new JsonApiClient();

export default function ArticleTitles() {
  const { data, error, isLoading } = useSWR(
    [
      'node--article',
      {
        queryString: new DrupalJsonApiParams()
          .addFilter('status', '1')
          .addSort('created', 'DESC')
          .addPageLimit(10)
          .getQueryString(),
      },
    ],
    ([type, options]) => client.getCollection(type, options),
  );

  if (error) return 'An error has occurred.';
  if (isLoading) return 'Loading...';

  return (
    <ul>
      {data.map((article) => (
        <li key={article.id}>
          <a
            href={getNodePath(article)}
            className="font-medium text-blue-600 dark:text-blue-500 hover:underline"
          >
            {article.title}
          </a>
        </li>
      ))}
    </ul>
  );
}
