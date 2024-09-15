# Documentation for Craft Web Controller Actions from JavaScript

This documentation provides details on how to interact with Craft CMS web controller actions using JavaScript. It includes a usage example, function signature, callback structure, error handling, and relevant scenarios for practical implementation.

## Overview

The provided JavaScript function allows you to call Craft web controller actions from the frontend and handle responses or errors efficiently. The function can display success or error notices to the user, depending on the result of the request.

## Usage

### Template

To use this functionality, include the necessary template code in your Twig template. For example:

```twig
{% include '@extras/_actions.twig' with {...} only %}
```

This inclusion ensures that the required JavaScript and assets are loaded on the page.

### JavaScript Function Signature

The main function used to call a Craft controller action is `window.Actions.postAction()`. Below is the signature and explanation of its parameters:

```javascript
window.Actions.postAction(action, data, callback, handleFailures = true, timeout = 10000)
```

#### Parameters

- **action** (`string`): The route to the Craft web controller.  
  Example: `"mymodule/mycontroller/myaction"`

- **data** (`object`): An object containing the parameters to be passed to the server. These will be sent as body parameters.  
  Example: `{'id': 1234, 'newTitle': 'My New Title'}`

- **callback** (`function`): A function to handle the server's response. You can define the callback with different argument combinations depending on your needs:
    - `() => {...}`
    - `data => {...}`
    - `(data, status, ok) => {...}`

- **handleFailures** (`boolean`, optional): If set to `false`, this allows you to handle `400` responses within the callback. The default is `true`.

- **timeout** (`number`, optional): The number of milliseconds after which the request will be aborted. Set to `0` if the request should never timeout. The default is `10,000 ms` (10 seconds).

### Callback Function Signature

The callback function is executed when the server responds. Its signature is:

```javascript
(data, status, ok)
```

#### Arguments

- **data** (`object`): The decoded JSON response from the server.
    - `data.message`: Contains the message returned by the controller action via `->asSuccess('message')` or `->asFailure('message')`.
    - `data.<key>`: Any additional data returned by the server.
    - `data.<modelName>`: Model data returned via `->asModelSuccess()` or `->asModelFailure()`.
    - `data.errors`: Validation errors returned via `->asModelFailure()`.

- **status** (`number`): The HTTP status code returned by the server (e.g., `200`, `400`, etc.).

- **ok** (`boolean`): A boolean indicating whether the request was successful (`true` if the status code is `200`).

### Default Behavior

By default, the callback function will only be called when the server responds with a status code `200`. In case of errors, they will be logged to the console and displayed as an error notice.

#### Possible Errors:

- Controller runtime errors
- Connection failures (e.g., server not running)
- Non-existing controller actions
- Uncaught exceptions thrown in the controller
- Failed `require...` constraints (e.g., `$this->requireAdmin()`)
- Timed-out requests
- Non-JSON responses (rare but possible)
- Responses with status code `400` (if `handleFailures = true`)

## Examples

### Example 1: Basic Success Handling

In this example, a controller action returns a success message, and the JavaScript code handles it by displaying a success notice.

#### Controller Action:

```php
public function actionMyAction()
{
    return $this->asSuccess('Done');
}
```

#### JavaScript:

```javascript
window.Actions.postAction("mymodule/mycontroller/myaction",
    {'id': 1234},
    data => {
        Actions.notice({type: 'success', text: data.message});
    }
);
```

### Example 2: Handling Failures in Callback

This example demonstrates handling both success and failure responses in the callback.

#### Controller Action:

```php
public function actionMyAction()
{
    if (...failed...) {
        return $this->asFailure('Something went wrong.');
    }
    
    return $this->asSuccess('Done');
}
```

#### JavaScript:

```javascript
window.Actions.postAction("mymodule/mycontroller/myaction",
    {'id': 1234},
    (data, status, ok) => {
        if (status === 200) {
            Actions.notice({type: 'success', text: data.message});
        } else {
            Actions.notice({type: 'error', text: data.message});
        }
    },
    false // handle failures in the callback
);
```

### Example 3: Handling Additional Data

This example shows how to handle additional data returned by the server.

#### Controller Action:

```php
public function actionMyAction()
{
    return $this->asSuccess(
        'This is a success with data.',
        [
            'foo' => 'bar',
            'baz' => 'qux',
        ]
    );
}
```

#### JavaScript:

```javascript
window.Actions.postAction("mymodule/mycontroller/myaction",
    {'id': 1234},
    data => {
        alert(data.message + ': Foo=' + data.foo);
    }
);
```

## Notes

- All JavaScript, HTML, and CSS are included directly in each page's source code for simplicity. However, if you want to include parts of the code in your own asset bundle or modify them, you can disable the corresponding part.
- Consider including the template only on pages where it is needed to avoid unnecessary asset loading.

### Example Twig Usage

```twig
{% do _globals.set('requireActions', true) %}

{% if _globals.get('requireActions') %}
    {% include '@extras/_actions.twig' with {} only %}
{% endif %}
```

This conditional inclusion ensures that the actions template is loaded only when needed.

---

This documentation covers the essential aspects of interacting with Craft controller actions via JavaScript. It provides flexibility in handling different success and error scenarios, making it adaptable to various use cases.