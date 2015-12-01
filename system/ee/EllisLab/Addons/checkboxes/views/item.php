<?php
	foreach ($options as $key => $value):

		$children = NULL;

		// If the value is an array, then we have children. Add them to the
		// queue with depth markers and set the real value to render the parent.
		if (is_array($value))
		{
			$children = $value['children'];
			$value = $value['name'];
		}

		$checked = (in_array(form_prep($value), $values)) ? TRUE : FALSE;

		$class = 'choice block';

		if ($checked)
		{
			$class .= ' chosen';
		}
?>
	<li>
		<label class="<?=$class?>">
			<?php if ($editable): ?>
				<span class="list-reorder"></span>
			<?php endif ?>
			<?=form_checkbox($field_name.'[]', $key, $checked)?> <?=$value?>
			<?php if ($editable): ?>
				<ul class="toolbar">
					<li class="edit"><a class="m-link" rel="modal-category-form" data-cat-group="<?=$cat_group_id?>" data-cat-id="<?=$key?>" href=""></a></li>
					<li class="remove"><a href=""></a></li>
				</ul>
			<?php endif ?>
		</label>
<?php
	if (isset($children)):
?>
		<ul>
			<?php $this->embed('item', array('options' => $children, 'values' => $values)); ?>
		</ul>
<?php
	endif;
?>
	</li>
<?php
endforeach;
?>
