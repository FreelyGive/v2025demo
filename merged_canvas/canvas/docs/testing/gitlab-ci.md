# GitLab CI

This project includes automated testing with GitLab CI, configured through
[.gitlab-ci.yml](../../.gitlab-ci.yml),.

Some jobs extend from the [drupal.org GitLab CI templates](https://git.drupalcode.org/project/gitlab_templates/-/blob/main/includes/include.drupalci.main.yml).

If you want to run a test locally exactly as it would be run in the CI, you can
do so by installing [gitlab-ci-local](https://github.com/firecow/gitlab-ci-local).
Then, run the following, replacing the job name where appropriate:

```shell
gitlab-ci-local \
        --remote-variables git@git.drupal.org:project/gitlab_templates=includes/include.drupalci.variables.yml=main \
        --variable="_GITLAB_TEMPLATES_REPO=project/gitlab_templates" "lint (php)"
```

## Tracked Files

Untracked and ignored files will not be synced inside isolated jobs, only tracked
files are synced, so remember to `git add` first.
