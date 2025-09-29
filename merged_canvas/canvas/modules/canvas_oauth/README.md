# Drupal Canvas OAuth

[OAuth 2](https://oauth.net/2) authentication for the external HTTP API of [Drupal Canvas](drupal.org/project/canvas),
currently covering endpoints for working with JavaScript/code components.

## 1. Requirements

* [Simple OAuth module](https://www.drupal.org/project/simple_oauth) (>=6.0.0)

## 2. Setup

### 2.1. Installation

Install the module; make sure [Simple OAuth (>=6.0.0)](https://www.drupal.org/project/simple_oauth) is already
installed, or is available for installation (i.e., after running `composer require drupal/simple_oauth:^6`).

### 2.2. Configuration

#### 2.2.1. Encryption keys

You need to generate an RSA key pair. This is required by the Simple OAuth module to encrypt tokens.

Example:

```
$ openssl genrsa -out private.key 2048
$ openssl rsa -in private.key -pubout > public.key
```

1. Store the keys at a secure location on your server, outside of your document root.
1. Configure the path to your keys at `/admin/config/people/simple_oauth`.

#### 2.2.2. Client

To interact with the API endpoints, you need a representation of a client. A client can request an access token with
certain scopes for authorization. (See the section "3. OAuth 2 scopes" below.) It's recommended to create separate
clients for different use cases, e.g., a CLI tool running in a development environment, or a script running on CI.

_Note: The Simple OAuth module uses the terms "client" and "consumer" interchangeably._

1. Visit `/admin/config/services/consumer`. Create a new client, or edit an existing one.
2. Enter or make note of your client ID.
3. If this is a new client, or if you're not aware of the client secret, enter a new one. Be sure to make note of it in
   a secure place.
4. Enable the [Client Credentials grant type](https://oauth.net/2/grant-types/client-credentials/). Canvas OAuth ships
   configuration and tests for this grant type. Other grant types can also work, but this module doesn't provide
   anything in particular for those.
  1. Select scopes that this client will be able to request. (See the section "3. OAuth 2 scopes" below.) For example,
     select `canvas:js_component` to give full access to working with code components; whereas `canvas:asset_library` will give
     full access to working with asset libraries.
  2. Configure a user who will be used as the author of actions made by this client. Two important notes:
    1. Do not use the anonymous user. Certain Canvas API endpoints require an authenticated user through an access checker,
       which will specifically look at the configured user here.
    2. Whatever user is configured here, the permissions that belong to the user's role(s) will be ignored for
       authorization.
5. Configure the access token expiration time. OAuth 2 client libraries usually handle the expiration and request new
   tokens as often as needed. Set the expiration time based on your security requirements. Shorter times (15-60 minutes)
   provide better security, while longer times (several hours) reduce the frequency of new access token requests.

### 2.3. Testing your setup

After following the steps above, you can request an access token. In the example below, the Drupal site has the base URL
of `https://canvas.ddev.site`, a client is configured with the client ID, `cli`, and with the client secret, `secret`. The
`canvas:js_component` scope is requested.

```
curl -X POST https://canvas.ddev.site/oauth/token \
  -d "grant_type=client_credentials&client_id=cli&client_secret=secret&scope=canvas:js_component"
```

The response will return the access token, which can be used with a request, e.g.:

```
curl  https://canvas.ddev.site/canvas/api/v0/config/js_component \
  -H "Authorization: Bearer YOUR-ACCESS-TOKEN"
```

## 3. OAuth 2 scopes

The Simple OAuth module allows various ways of defining OAuth 2 scopes through its concept of scope providers. However,
there can only be a single active scope provider selected for a Drupal site using Simple OAuth. Choosing **dynamic
scopes** is the easiest, and probably the most widespread approach, as it makes use of a config entity, and manages the
scopes via a UI. Therefore, this is how Canvas OAuth provides a set of default OAuth 2 scopes.

The following scopes are created as dynamic scopes (config entities) upon installing the module:

| Scope              | Permission                   |
|--------------------|------------------------------|
| `canvas:js_component`   | `administer code components` |
| `canvas:asset_library` | `administer code components` |

Each scope is enabled for the [Client Credentials grant type](https://oauth.net/2/grant-types/client-credentials/).

You can change this configuration, e.g., associate user roles instead of permissions, or simplify/merge scopes. Consider
this set of scopes as an initial batch that aims to balance simplicity with future-proofing for when Canvas ships more
granular permissions.

Also feel free to incorporate these into another scope provider: What's important is the `administer code components`
permission.

## 4. Supported endpoints

→ See more details in [Canvas' OpenAPI spec](https://git.drupalcode.org/project/canvas/-/blob/1.x/openapi.yml).

| Method                   | Endpoint                                              |
|--------------------------|-------------------------------------------------------|
| `GET`, `POST`            | `/canvas/api/v0/config/js_component`                      |
| `GET`, `PATCH`, `DELETE` | `/canvas/api/v0/config/js_component/{configEntityId}`     |
| `GET`, `POST`            | `/canvas/api/v0/config/asset_library`                  |
| `GET`, `PATCH`, `DELETE` | `/canvas/api/v0/config/asset_library/{configEntityId}` |
