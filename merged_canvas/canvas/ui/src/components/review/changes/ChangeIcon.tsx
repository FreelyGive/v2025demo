import TemplateIcon from '@assets/icons/template.svg?react';
import {
  CodeIcon,
  Component1Icon,
  CubeIcon,
  FileTextIcon,
  HomeIcon,
} from '@radix-ui/react-icons';

import { useAppSelector } from '@/app/hooks';
import { selectHomepagePath } from '@/features/configuration/configurationSlice';

import styles from './ChangeRow.module.css';

interface ChangeIconProps {
  entityType: string;
  entityId: string | number;
  className?: string;
}

const ChangeIcon: React.FC<ChangeIconProps> = ({ entityType, entityId }) => {
  const homepagePath = useAppSelector(selectHomepagePath);
  const iconClass = styles.changeIcon;

  switch (entityType) {
    case 'js_component':
      return <Component1Icon className={iconClass} />;
    case 'asset_library':
      return <CodeIcon className={iconClass} />;
    case 'page_region':
      return <CubeIcon className={iconClass} />;
    case 'content_template':
      return <TemplateIcon className={iconClass} />;
    case 'staged_config_update':
      // Currently the only staged config update supported is setting
      // the homepage.
      return <HomeIcon className={iconClass} />;
    case 'canvas_page':
      if (homepagePath === `/page/${entityId}`) {
        return <HomeIcon className={iconClass} />;
      } else {
        return <FileTextIcon className={iconClass} />;
      }
    default:
      return <FileTextIcon className={iconClass} />;
  }
};

export default ChangeIcon;
