<?php

namespace wsydney76\extras\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\Event;
use craft\commerce\elements\Product;
use craft\elements\Entry;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;

class DraftsHelper extends Component
{
    public function initDraftsHelper()
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(\yii\base\Event $event) {

                /** @var CraftVariable $variable */
                $variable = $event->sender;

                $variable->set('compare', CompareService::class);
            }
        );


        Event::on(
            Element::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML, function(DefineHtmlEvent $event) {
            if ($event->sender instanceof Entry || $event->sender instanceof Product) {
                $event->html =
                    Craft::$app->view->renderTemplate('_extras/entry_hasdrafts.twig', [
                        'entry' => $event->sender
                    ]) . $event->html;

                if (!Craft::$app->request->isAjax) {
                    $event->html .= Craft::$app->view->renderTemplate('_extras/draft_hints.twig', [
                        'entry' => $event->sender
                    ]);
                }
            }
        });

        // Register element index column
        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_TABLE_ATTRIBUTES, function(RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes['hasDrafts'] = ['label' => Craft::t('_extras', 'Has Drafts')];
        });

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_ATTRIBUTE_HTML, function(DefineAttributeHtmlEvent $event) {

            if ($event->attribute === 'hasDrafts') {
                $event->handled = true;
                /** @var Entry $entry */
                $entry = $event->sender;
                $event->html = '';

                $query = Entry::find()
                    ->draftOf($entry)
                    ->provisionalDrafts(true)
                    ->site($entry->site)
                    ->status(null);

                $countProvisionalDrafts = $query->count();

                $query->draftCreator(Craft::$app->user->identity);
                $hasOwnProvisionalDraft = $query->exists();


                if (Craft::$app->user->identity->can('viewpeerprovisionaldrafts')) {
                    // Workaround because there is no ->draftCreator('not ...)
                    if ($hasOwnProvisionalDraft) {
                        --$countProvisionalDrafts;
                    }
                }

                $countDrafts = Entry::find()
                    ->draftOf($entry)
                    ->drafts(true)
                    ->site($entry->site)
                    ->status(null)
                    ->count()
                ;

                if ($hasOwnProvisionalDraft || $countProvisionalDrafts > 0 || $countDrafts > 0) {
                    $event->html .= Craft::$app->view->renderTemplate('_extras/_drafts_indexcolumn', [
                        'hasOwnProvisionalDraft' => $hasOwnProvisionalDraft,
                        'countProvisionalDraftsByOtherUsers' => $countProvisionalDrafts,
                        'countDrafts' => $countDrafts,
                    ]);
                }
            }
        });

        // Register element index column
        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_TABLE_ATTRIBUTES, function(RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes['hasOwnProvisionalDraft'] = ['label' => Craft::t('_extras', 'Open Edit')];
        });

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_ATTRIBUTE_HTML, function(DefineAttributeHtmlEvent $event) {

            if ($event->attribute === 'hasOwnProvisionalDraft') {
                $event->handled = true;
                /** @var Entry $entry */
                $entry = $event->sender;
                $event->html = '';

                $hasOwnProvisionalDraft = Entry::find()
                    ->draftOf($entry)
                    ->provisionalDrafts(true)
                    ->site($entry->site)
                    ->status(null)
                    ->draftCreator(Craft::$app->user->identity)
                    ->exists();


                if ($hasOwnProvisionalDraft) {
                    $event->html .= Craft::$app->view->renderTemplate('_extras/_drafts_indexcolumn', [
                        'hasOwnProvisionalDraft' => $hasOwnProvisionalDraft,
                        'countProvisionalDraftsByOtherUsers' => 0,
                        'countDrafts' => 0,
                    ]);
                }
            }
        });
    }

    public function createPermissions()
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $event->permissions['Extras Plugin'] = [
                'heading' => 'Extras Plugin',
                'permissions' => [
                    'accessPlugin-_extras' => [
                        'label' => Craft::t('_extras', 'Access Extras Plugin'),
                    ],
                    'viewpeerprovisionaldrafts' => [
                        'label' => Craft::t('_extras', 'View provisional drafts of other users')
                    ],
                    'transferprovisionaldrafts' => [
                        'label' => Craft::t('_extras', 'Transfer other users provisional draft to own account')
                    ]
                ]

            ];
        });
    }
}