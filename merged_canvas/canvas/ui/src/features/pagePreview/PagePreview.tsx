import { useCallback, useEffect, useState } from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import { useParams } from 'react-router';
import { AlertDialog, Button, Flex } from '@radix-ui/themes';

import { useAppSelector } from '@/app/hooks';
import {
  selectLayout,
  selectModel,
  selectUpdatePreview,
} from '@/features/layout/layoutModelSlice';
import { selectPageData } from '@/features/pageData/pageDataSlice';
import { selectPreviewHtml } from '@/features/pagePreview/previewSlice';
import { usePostPreviewMutation } from '@/services/preview';
import { viewportSizes } from '@/types/Preview';

import styles from './PagePreview.module.css';

const PagePreview = () => {
  const layout = useAppSelector(selectLayout);
  const updatePreview = useAppSelector(selectUpdatePreview);
  const model = useAppSelector(selectModel);
  const entity_form_fields = useAppSelector(selectPageData);
  const frameSrcDoc = useAppSelector(selectPreviewHtml);
  const [postPreview] = usePostPreviewMutation();
  const { showBoundary } = useErrorBoundary();
  const [widthVal, setWidthVal] = useState('100%');
  const { width } = useParams();
  const [linkIntercepted, setLinkIntercepted] = useState('');
  const [submissionIntercepted, setSubmissionIntercepted] = useState(false);

  useEffect(() => {
    const sendPreviewRequest = async () => {
      try {
        await postPreview({
          layout,
          model,
          entity_form_fields,
        }).unwrap();
      } catch (err) {
        showBoundary(err);
      }
    };
    if (updatePreview) {
      sendPreviewRequest().then(() => {});
    }
  }, [
    layout,
    model,
    postPreview,
    entity_form_fields,
    updatePreview,
    showBoundary,
  ]);

  useEffect(() => {
    if (!width || width === 'full') {
      setWidthVal('100%');
    } else {
      viewportSizes.find((vs) => {
        if (width === vs.id) {
          setWidthVal(`${vs.width}px`);
          return true;
        }
      });
    }
  }, [width]);

  useEffect(() => {
    function handlePreviewLinkClick(event: MessageEvent) {
      if (event.data && event.data.canvasPreviewClickedUrl) {
        setLinkIntercepted(event.data.canvasPreviewClickedUrl);
      }
      if (event.data && event.data.canvasPreviewFormSubmitted) {
        setSubmissionIntercepted(true);
      }
    }
    window.addEventListener('message', handlePreviewLinkClick);

    return () => {
      window.removeEventListener('message', handlePreviewLinkClick);
    };
  });

  const handleDialogOpenChange = (isOpen: boolean) => {
    if (!isOpen) {
      setLinkIntercepted('');
      setSubmissionIntercepted(false);
    }
  };

  const handleLinkOpenClick = useCallback(() => {
    window.open(linkIntercepted, '_blank');
  }, [linkIntercepted]);

  return (
    <>
      <div className={styles.PagePreviewContainer}>
        <div className={styles.controls}></div>
        <iframe
          title="Page preview"
          style={{ width: widthVal }}
          srcDoc={frameSrcDoc}
          className={styles.PagePreviewIframe}
        ></iframe>
      </div>
      <AlertDialog.Root
        open={!!linkIntercepted || submissionIntercepted}
        defaultOpen={false}
        onOpenChange={handleDialogOpenChange}
      >
        <AlertDialog.Content maxWidth="450px">
          {linkIntercepted && (
            <>
              <AlertDialog.Title>Link clicked</AlertDialog.Title>
              <AlertDialog.Description size="2" mb="4">
                You attempted to open a link in the preview but it was
                intercepted before you were navigated away from this page.
              </AlertDialog.Description>

              <AlertDialog.Description size="2">
                The link goes to <strong>{linkIntercepted}</strong>
              </AlertDialog.Description>

              <Flex gap="3" mt="4" justify="end">
                <AlertDialog.Cancel>
                  <Button variant="soft" color="gray">
                    Close
                  </Button>
                </AlertDialog.Cancel>
                <AlertDialog.Action>
                  <Button
                    variant="solid"
                    color="blue"
                    onClick={handleLinkOpenClick}
                  >
                    Open in new window
                  </Button>
                </AlertDialog.Action>
              </Flex>
            </>
          )}
          {submissionIntercepted && (
            <>
              <AlertDialog.Title>Form submitted</AlertDialog.Title>
              <AlertDialog.Description size="2" mb="4">
                You attempted to submit a form in the preview but it was
                intercepted before you were navigated away from this page.
              </AlertDialog.Description>

              <Flex gap="3" mt="4" justify="end">
                <AlertDialog.Cancel>
                  <Button variant="soft" color="gray">
                    Close
                  </Button>
                </AlertDialog.Cancel>
              </Flex>
            </>
          )}
        </AlertDialog.Content>
      </AlertDialog.Root>
    </>
  );
};

export default PagePreview;
