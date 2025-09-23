import { getPageData } from '@/lib/drupal-utils';

const PageTitle = () => {
  const { pageTitle } = getPageData();
  if (!pageTitle) {
    return null;
  }
  return <h1>{pageTitle}</h1>;
};

export default PageTitle;
