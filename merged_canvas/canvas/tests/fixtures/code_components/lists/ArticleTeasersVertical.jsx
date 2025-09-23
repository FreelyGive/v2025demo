import { DrupalJsonApiParams } from 'drupal-jsonapi-params';
import useSWR from 'swr';
import { JsonApiClient } from '@drupal-api-client/json-api-client';

import { getNodePath } from '@/lib/jsonapi-utils';

const client = new JsonApiClient();
const defaultImage =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAQAAAAEACAIAAADTED8xAAADMElEQVR4nOzVwQnAIBQFQYXff81RUkQCOyDj1YOPnbXWPmeTRef+/3O/OyBjzh3CD95BfqICMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMO0TAAD//2Anhf4QtqobAAAAAElFTkSuQmCC';

export default function ArticleTeasersVertical() {
  const { data, error, isLoading } = useSWR(
    [
      'node--article',
      {
        queryString: new DrupalJsonApiParams()
          .addInclude(['field_image'])
          .addFilter('status', '1')
          .addSort('created', 'DESC')
          .addPageLimit(4)
          .getQueryString(),
      },
    ],
    ([type, options]) => client.getCollection(type, options),
  );

  if (error) return 'An error has occurred.';
  if (isLoading) return 'Loading...';

  return (
    <ul className="max-w-md">
      {data.map((article) => (
        <li key={article.id} className="pb-3 sm:pb-4">
          <div className="flex items-center space-x-4 rtl:space-x-reverse">
            <div className="shrink-0">
              <img
                src={article.field_image?.uri?.url || defaultImage}
                className="h-25 w-25 object-fill rounded-l"
              />
            </div>
            <div className="flex-1 min-w-0">
              <h3 className="text-sm font-medium text-gray-900 truncate dark:text-white">
                {article.title}
              </h3>
              <p className="text-sm text-gray-500 truncate dark:text-gray-400">
                <a
                  href={getNodePath(article)}
                  className="font-medium text-blue-600 dark:text-blue-500 hover:underline"
                >
                  Learn more &#x3009;
                </a>
              </p>
            </div>
          </div>
        </li>
      ))}
    </ul>
  );
}
