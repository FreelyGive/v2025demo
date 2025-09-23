import { DrupalJsonApiParams } from 'drupal-jsonapi-params';
import useSWR from 'swr';
import { JsonApiClient } from '@drupal-api-client/json-api-client';

import { getNodePath } from '@/lib/jsonapi-utils';

const client = new JsonApiClient();
const defaultImage =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAQAAAAEACAIAAADTED8xAAADMElEQVR4nOzVwQnAIBQFQYXff81RUkQCOyDj1YOPnbXWPmeTRef+/3O/OyBjzh3CD95BfqICMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMK0CMO0TAAD//2Anhf4QtqobAAAAAElFTkSuQmCC';

const formatSummary = (node) =>
  node.body?.summary
    ? node.body.summary.length > 100
      ? node.body.summary.slice(0, 100) + '…'
      : node.body.summary
    : '';
const numberOfArticles = 16;

// Create loading placeholders to avoid layout shift.
let articles = Array(numberOfArticles)
  .fill()
  .map((_, index) => ({
    id: index,
  }));

export default function ArticleTeasersGrid() {
  const { data, error, isLoading } = useSWR(
    [
      'node--article',
      {
        queryString: new DrupalJsonApiParams()
          .addInclude(['field_image'])
          .addFilter('status', '1')
          .addSort('created', 'DESC')
          .addPageLimit(numberOfArticles)
          .getQueryString(),
      },
    ],
    ([type, options]) => client.getCollection(type, options),
  );

  if (!isLoading && !error) {
    articles = data;
  }

  return (
    <ul className="grid grid-cols-1 md:grid-cols-4 gap-4">
      {articles.map((article) => (
        <li key={article.id} className="h-auto max-w-full">
          <div className="h-full flex flex-col max-w-sm bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <img
              className="rounded-t-lg object-fill w-full h-50"
              src={article.field_image?.uri?.url || defaultImage}
              alt={article.field_image?.resourceIdObjMeta.alt}
              title={article.field_image?.resourceIdObjMeta.title}
            />
            <div className="p-5 flex-grow flex flex-col">
              <h5 className="mb-2 text-2xl h-[4lh] overflow-hidden font-bold tracking-tight text-gray-900 dark:text-white">
                {article.title}
              </h5>
              <p className="mb-3 h-[8lh] font-normal text-gray-700 dark:text-gray-400">
                {formatSummary(article)}
              </p>
              <div className="inline-flex mt-auto">
                <a
                  href={getNodePath(article)}
                  className="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                >
                  Read more
                  <svg
                    className="rtl:rotate-180 w-3.5 h-3.5 ms-2"
                    aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 14 10"
                  >
                    <path
                      stroke="currentColor"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M1 5h12m0 0L9 1m4 4L9 9"
                    />
                  </svg>
                </a>
              </div>
            </div>
          </div>
        </li>
      ))}
    </ul>
  );
}
