# Light Contact Form

Create a simple contact form widget within WordPress with an optional Autoresponder. Due to the low level implementation you have great freedom in customizing the contact form to your needs using CSS and Javascript.

## Getting Started

1. Download the repo, move it to your plugins folder and activate the plugin in the back end of your WordPress instance.
2. Open the settings of the *Light Contact Form* in the back end of your WordPress instance and check all corresponding input fields and click *Save*.
3. Open the lucky post or page in the back end of your WordPress instance.
4. Add the following example code to the content area:
```
[jw_lightcontactform_make]
	  <input data-name type="text">
	  <input data-mail type="text">
	  <textarea data-snippet></textarea>
[/jw_lightcontactform_make]
```
5. Done! Give it a try in the front end.

## Customize widget

As you might have realized, the html-elements within the shortcode come with special html-attributes in order to easily identify every input field within the contact form.

### data-name

This html-attribute will cause the plugin to associate the corresponding input field with the name of the sender.

### data-mail

This html-attribute will cause the plugin to associate the corresponding input field with the mail of the sender.

### data-snippet

This html-attribute will cause the plugin to associate all corresponding input fields with a message snippet of the sender. You can have as many snippets as you like! All those snippets will be aggregated into the mail's body. You want to have a customized text before or after each of the snippet? Just define it as shown below:
```
[jw_lightcontactform_make]
		<input placeholder="How old are you?" data-snippet="The senders age: value()">
		<textarea placeholder="Your message" data-snippet="The message of the sender:newline()newline()value()"></textarea>
[/jw_lightcontactform_make]
```

### data-snippet-order

The order of your snippets in the contact form is different towards the order of appearance within the mail's body? Change it easily by defining the html-attribute *data-snippet-order* like this:
```
[jw_lightcontactform_make]
		<input placeholder="Field 1" data-snippet data-snippet-order="1">
		<input placeholder="Field 3" data-snippet>
		<textarea placeholder="Field 2" data-snippet data-snippet-order="2"></textarea>
[/jw_lightcontactform_make]
```
Any fields without this html-attribute will be appended at the end.
