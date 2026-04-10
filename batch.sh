drush php:eval "
\$config = \Drupal::configFactory()->getEditable('search_api.index.alicepeio_index');
\$data = \$config->getRawData();

\$json = json_encode(\$data);
\$json = str_replace('entity:group_content', 'entity:group_relationship', \$json);
\$new_data = json_decode(\$json, TRUE);

\$config->setData(\$new_data)->save();

echo 'Remplacement effectué.' . PHP_EOL;
"
