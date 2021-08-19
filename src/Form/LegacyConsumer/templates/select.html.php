<?php /** @var Give\Framework\FieldsAPI\Select $field */ ?>
<label>
	<?php echo $field->getLabel(); ?>
	<select
		name="give_<?php echo $field->getName(); ?>"
		<?php echo $field->getAllowMultiple() ? 'multiple' : ''; ?>
	>
		<?php if ( $placeholder = $field->getPlaceholder() ) : ?>
			<option value=""><?php echo $placeholder; ?></option>
		<?php endif; ?>
		<?php foreach ( $field->getOptions() as $option ) : ?>
			<?php $label = $option->getLabel(); ?>
			<?php $value = $option->getValue(); ?>
			<?php $default = $field->getDefaultValue() === $option->getValue(); ?>
			<option
				<?php echo $label ? "value={$value}" : ''; ?>
				<?php echo $default ? 'selected' : ''; ?>
			>
				<?php echo $label ?: $value; ?>
			</option>
		<?php endforeach; ?>
	</select>
</label>
