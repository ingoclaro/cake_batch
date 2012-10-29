cake_batch
==========

Batch behavior to load csv files line by line to the database

## Install
copy batch.php to app/models/behaviors


## Use

add the behavior to your model:

```php
var $actsAs = array(
	'Batch' => array(
		'header' => array('user_id', 'title'),
		'delimiter' => ';',
	)
);
```

## Parameters

**header** (optional):

if header isn't provided, it uses the first line as header
if header is provided and the file has the same hader, it's ignored

**delimiter** (optional):

delimiter is optional, if not provided it's auto detected
blank lines or lines with only the delimiter (and no data) are ignored.
use 'auto' for auto detecting delimiter.

**hash** (optional):

pass true to calculate the hash (sha1) of the file. This can be used to avoid
duplicate processing of files based on content.


## Methods

### batchValidate

call batchValidate($file) to validate the data

data is validated using Cake validation, so you should declare validation info in your model
@see http://book.cakephp.org/view/1143/Data-Validation

### batchProcess
call batchProcess($file, $callback, $extra) to process the data. callback will be called for each line, with the data and extra parameters

you should use the following template for the callback function in your model (function name is up to you):

```php
function batchProcessCallback($data, $extra = null)
```

dynamic validation with extra data:

sometimes you have data that is passed as arg instead of the csv file
in that cases you can create dynamic validation rules in the controller:

```php
$this->Model->validate['your_field'] = array(
	'your_rule' => array(
		'rule' => array('validation_method_in_model', 'extra_parameter1', 'extra_parameter2'),
		'message' => 'Default error message for this rule.',
	)
);
```

in your model create the validation method:
@see http://book.cakephp.org/view/1179/Custom-Validation-Rules

```php
function validation_method_in_model($check, $param1, $param2) {
	$value = array_shift($check);

	return false; //validation failed, use default error message
	return 'custom error message';
	return true; //validation ok.
}
```
