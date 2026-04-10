# Using TFA with REST

If your site provides a REST API, end-users need a way to access the API
endpoints which can be more challenging with TFA enabled. These approaches can
be taken:

- Use an API token auth provider (recommended)
- Append two-factor code to the password field

## Use an API token auth provider (recommended)

The recommended approach is to use an API token authentication provider that
does not rely on the user authentication such as:

- [JSON Web Token Authentication (JWT)](https://www.drupal.org/project/jwt)
- [Key Auth](https://www.drupal.org/project/key_auth)

## Appending two-factor code to password

When providing the password via Cookie Authentication or Oauth methods, the
end user can append the two factor authentication code to their password.
Example: "my-password123456" where 123456 is the second factor code.
