<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorProjectMembersEditController
  extends PhabricatorProjectController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }
    $profile = $project->loadProfile();
    if (empty($profile)) {
      $profile = new PhabricatorProjectProfile();
    }

    $member_phids = $project->loadMemberPHIDs();

    $errors = array();
    if ($request->isFormPost()) {
      $changed_something = false;
      $member_map = array_fill_keys($member_phids, true);

      $remove = $request->getStr('remove');
      if ($remove) {
        if (isset($member_map[$remove])) {
          unset($member_map[$remove]);
          $changed_something = true;
        }
      } else {
        $new_members = $request->getArr('phids');
        foreach ($new_members as $member) {
          if (empty($member_map[$member])) {
            $member_map[$member] = true;
            $changed_something = true;
          }
        }
      }

      $xactions = array();
      if ($changed_something) {
        $xaction = new PhabricatorProjectTransaction();
        $xaction->setTransactionType(
          PhabricatorProjectTransactionType::TYPE_MEMBERS);
        $xaction->setNewValue(array_keys($member_map));
        $xactions[] = $xaction;
      }

      if ($xactions) {
        $editor = new PhabricatorProjectEditor($project);
        $editor->setActor($user);
        $editor->applyTransactions($xactions);
      }

      return id(new AphrontRedirectResponse())
        ->setURI($request->getRequestURI());
    }

    $member_phids = array_reverse($member_phids);
    $handles = $this->loadViewerHandles($member_phids);

    $state = array();
    foreach ($handles as $handle) {
      $state[] = array(
        'phid' => $handle->getPHID(),
        'name' => $handle->getFullName(),
      );
    }

    $header_name = 'Edit Members';
    $title = 'Edit Members';

    $list = $this->renderMemberList($handles);

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setName('phids')
          ->setLabel('Add Members')
          ->setDatasource('/typeahead/common/users/'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/project/view/'.$project->getID().'/')
          ->setValue('Add Members'));
    $faux_form = id(new AphrontFormLayoutView())
      ->setBackgroundShading(true)
      ->setPadded(true)
      ->appendChild(
        id(new AphrontFormInsetView())
          ->setTitle('Current Members ('.count($handles).')')
          ->appendChild($list));

    $panel = new AphrontPanelView();
    $panel->setHeader($header_name);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);
    $panel->appendChild('<br />');
    $panel->appendChild($faux_form);

    $nav = $this->buildLocalNavigation($project);
    $nav->selectFilter('members');
    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => $title,
      ));
  }

  private function renderMemberList(array $handles) {
    $request = $this->getRequest();
    $user = $request->getUser();
    $list = id(new PhabricatorObjectListView())
      ->setHandles($handles);

    foreach ($handles as $handle) {
      $hidden_input = phutil_render_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => 'remove',
          'value' => $handle->getPHID(),
        ),
        '');

      $button = javelin_render_tag(
        'button',
        array(
          'class' => 'grey',
        ),
        pht('Remove'));

      $list->addButton(
        $handle,
        phabricator_render_form(
          $user,
          array(
            'method' => 'POST',
            'action' => $request->getRequestURI(),
          ),
          $hidden_input.$button));
    }

    return $list;
  }
}
