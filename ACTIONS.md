### How to Use the Craft CMS Actions Twig Component

This documentation explains how to use the `@extras/_actions.twig` component in Craft CMS to call web controller actions via JavaScript and display success or error notices.

---

### Component Overview

The `Actions` component allows you to make asynchronous requests to Craft CMS web controller actions and handle responses within your JavaScript code. Additionally, the component can display success or error notifications to users based on the outcome of the requests.

#### Table of Contents
- [Usage Overview](#usage-overview)
- [JavaScript Methods](#javascript-methods)
    - [window.Actions.postAction()](#windowactionspostaction)
- [Callback Signature](#callback-signature)
- [Example Usage](#example-usage)
    - [Basic Success Example](#basic-success-example)
    - [Handling Failures in the Callback](#handling-failures-in-the-callback)
    - [Using Additional Data](#using-additional-data)
- [Notices System](#notices-system)
    - [Customizing Notices](#customizing-notices)

---

### Usage Overview

To include the `Actions` component in your Craft CMS templates, use the following Twig code:

```twig
{% include '@extras/_actions.twig' with {...} only %}
```

Ensure that you include this component only on pages where it is necessary to avoid unnecessary JS/CSS loading.

---

### JavaScript Methods

#### `window.Actions.postAction()`

This method allows you to send a POST request to a Craft CMS web controller action.

**Syntax:**
```javascript
window.Actions.postAction(action, data, callback, options = {})
```

**Parameters:**
- `action` (String): The route to the controller action (e.g., `mymodule/mycontroller/myaction`).
- `data` (Object): Key-value pairs of parameters to be passed to the server.
- `callback` (Function): Function to handle the response. It can be in the form:
    - `() => {...}`
    - `data => {...}`
    - `(data, status, ok) => {...}`
- `options` (Object, Optional): Additional settings for the request:
    - `handleFailuresInCallback` (Boolean, default: `false`): Set to `true` if you want to handle `400` responses in the callback.
    - `timeout` (Number, default: `20000`): The number of milliseconds after which the request is aborted. Set to `0` for no timeout.
    - `logLevel` (String, default: `'none'`): Set to `'info'` to log responses for debugging purposes.
    - `indicatorSelector` (?String, default: `null`): A unique CSS selector of the HTML element to provide user feedback while the request is in progress, e.g. a spinner.
    - `indicatorClass` (String, default: `fetch-request`): The class to apply to the indicator element while the request is in progress.

---

### Callback Signature

The callback provided to `postAction` is invoked with the following parameters:

```javascript
function callback(data, status, ok) {
    // Handle response
}
```

- `data` (Object): Decoded JSON response from the server.
    - `data.message`: Success or error message returned from the server.
    - `data.<key>`: Additional data returned from the server.
    - `data.<modelName>`: Model data returned from server via `->asModelSuccess()`. `->asModelFailure()`.
    - `data.errors`: Validation errors for models (if any).
- `status` (Number): HTTP status code (e.g., `200`, `400`).
- `ok` (Boolean): Indicates whether the request was successful (`true` for success, `false` for errors).

#### Handling Errors

By default, the callback will only be called if the server responds with a status code "200",
so that you don't have to care about any errors in your client code.

Errors will be (optionally) logged to console and displayed via an error notice:
- Controller runtime errors
- Connection failure (server not running)
- Non-existing controller actions
- Uncaught exceptions thrown in controller action
- Failed 'require...' constraints (like $this->requireLogin())
- Timed out requests
- Non-JSON responses (that should never happen...)
- Responses with status code 400, like failed controller actions (`return $this->asFailure(...)`), if handleFailuresInCallback = false (default)

Note that errors may be different depending on Craft environment (dev, staging, production, devMode=on/off).

#### Handling Success

User feedback for successful actions is up to you, you may call `Action.notice({type:'success', text: data.message)` inside your callback, or use any other method in order to provide visual feedback.

---

### Example Usage

#### Basic Success Example

This example demonstrates sending a POST request and handling a success response.

**Controller Action:**
```php
return $this->asSuccess('Action completed successfully');
```

**JavaScript:**
```javascript
window.Actions.postAction("mymodule/mycontroller/myaction",
    {'id': 1234},
    (data) => {
        // Do somthing with the data
        Actions.notice({ type: 'success', text: data.message });
    }
);
```

#### Handling Failures in the Callback

If you want to handle failures (status `400`) directly in your callback, set `handleFailuresInCallback` to `true` in the options.


**Controller Action:**
```php
if (...someErrorCondition...) {
    return $this->asFailure('An error occurred');
}
return $this->asSuccess('Action completed successfully');
```

**JavaScript:**
```javascript
window.Actions.postAction("mymodule/mycontroller/myaction",
    {'id': 1234},
    (data, status, ok) => {
        if (ok) {
            // Do somthing with the data
            Actions.notice({ type: 'success', text: data.message });
        } else {
            // cleanup...
            Actions.notice({ type: 'error', text: data.message });
        }
    },
    { handleFailuresInCallback: true }
);
```

#### Using Additional Data

If the controller returns additional data, you can access it in the callback.

**Controller Action:**
```php
return $this->asSuccess('Success message', ['foo' => 'bar']);
```

**JavaScript:**
```javascript
window.Actions.postAction("mymodule/mycontroller/myaction",
    {'id': 1234},
    (data) => {
        // Do somthing with the data
        alert(data.message + ': Foo=' + data.foo);
    }
);
```

---

#### Using Indicator

Display an indicator while the request is in progress.

Requires an HTML element that can be queried by the CSS selector specified in `indicatorSelector`, where the presence of the class specified in `indicatorClass` in some way toggles visibility.

`document.querySelector()` is used internally to find the element, so technically the selector can be anything that works with this method, however using an `id` is best practice.

**JavaScript:**
```javascript
window.Actions.postAction("mymodule/mycontroller/myaction",
    {'id': 1234},
    (data) => {
        // Do somthing with the data
        Actions.notice({ type: 'success', text: data.message });
    },
    {indicatorSelector: '#my-indicator', indicatorClass: 'my-indicator-class'}
);
```

**HTML (Example)**
```html
<div id="my-indicator" class="styling-the-indicator">Loading...</div>
```

**CSS (Example)**
```css
#my-indicator  {
    display: none;
}

#my-indicator.my-indicator-class {
    display: block;
}
```

---

### Notices System

The `Actions` component includes a built-in system for displaying notifications to the user.

**Triggering Notices:**
- Use `Actions.notice({ type: 'success', text: 'Your message here' })` to display a notification.

**Types of notices:**
- `success`
- `info`
- `warning`
- `error`

#### Customizing Notices

You can customize the appearance and behavior of notices using CSS and JavaScript parameters.

**Default Notice CSS Classes:**
```css
.notice-success {
    background-color: #16a34a;
    color: white;
}
.notice-error {
    background-color: #dc2626;
    color: white;
}
.notice-info {
    background-color: #2563eb;
    color: white;
}
.notice-warning {
    background-color: #ea580c;
    color: white;
}
```

**Example Notice Initialization:**
```javascript
const noticesHandler = new NoticesHandler({
    duration: 5000,  // Time in milliseconds the notice stays visible
    classes: {
        wrapper: 'custom-wrapper-class',
        item: 'custom-item-class',
        success: 'custom-success-class'
    }
});
```

---

By following these guidelines, you can efficiently use the `Actions` component in Craft CMS to interact with your controllers and display feedback to users via notices.