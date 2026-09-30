<template>
  <ion-page>
    <!-- Main Header -->
    <ion-header :translucent="true">
      <ion-toolbar>
        <ion-title>Blank</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true">
      <!-- Collapsed Header -->
      <ion-header collapse="condense">
        <ion-toolbar>
          <ion-title size="large">Blank</ion-title>
        </ion-toolbar>
      </ion-header>

      <div id="container">
        <!-- Introduction -->
        <strong>Ready to create an app?</strong>

        <p>
          Start with Ionic
          <a
            target="_blank"
            rel="noopener noreferrer"
            href="https://ionicframework.com/docs/components"
          >
            UI Components
          </a>
        </p>

        <!-- Breadcrumbs -->
        <ion-breadcrumbs>
          <ion-breadcrumb href="#home">Home</ion-breadcrumb>
          <ion-breadcrumb href="#about us">About us</ion-breadcrumb>
          <ion-breadcrumb href="#contact">Contact</ion-breadcrumb>
          <ion-breadcrumb href="#review">Review</ion-breadcrumb>
        </ion-breadcrumbs>

        <!-- Inner Header -->
        <ion-header>
          <ion-toolbar>
            <ion-title>Header</ion-title>
          </ion-toolbar>
        </ion-header>

        <!-- Headings -->
        <ion-content class="ion-padding">
          <h1>Heading 1</h1>
          <h2>Heading 2</h2>
          <h3>Heading 3</h3>
          <h4>Heading 4</h4>
          <h5>Heading 5</h5>
          <h6>Heading 6</h6>

          <p>
            Here's a small text description for the content.
            Nothing more, nothing less.
          </p>
        </ion-content>

        <!-- Progress Bar -->
        <ion-progress-bar type="indeterminate"></ion-progress-bar>

        <!-- Card -->
        <ion-card>
          <ion-card-header>
            <ion-card-title>Card Title</ion-card-title>
            <ion-card-subtitle>Card Subtitle</ion-card-subtitle>
          </ion-card-header>

          <ion-card-content>
            Here's a small text description for the card content.
            Nothing more, nothing less.
          </ion-card-content>
        </ion-card>

        <!-- Chips -->
        <ion-chip>
          <ion-avatar>
            <img
              alt="Silhouette of a person's head"
              src="https://ionicframework.com/docs/img/demos/avatar.svg"
            />
          </ion-avatar>

          <ion-label>Avatar Chip</ion-label>

          <ion-icon :icon="closeCircle"></ion-icon>
        </ion-chip>

        <ion-chip>
          <ion-icon
            :icon="pin"
            color="primary"
          ></ion-icon>

          <ion-label>Icon Chip</ion-label>

          <ion-icon :icon="close"></ion-icon>
        </ion-chip>

        <!-- Badges -->
        <ion-list>
          <ion-item>
            <ion-badge slot="start">11</ion-badge>
            <ion-label>Badge in start slot</ion-label>
          </ion-item>

          <ion-item>
            <ion-badge slot="end">22</ion-badge>
            <ion-label>Badge in end slot</ion-label>
          </ion-item>
        </ion-list>

        <!-- Floating Action Button -->
        <ion-fab
          slot="fixed"
          horizontal="center"
          vertical="center"
        >
          <ion-fab-button>
            <ion-icon :icon="add"></ion-icon>
          </ion-fab-button>

          <ion-fab-list side="top">
            <ion-fab-button>
              <ion-icon :icon="chevronUp"></ion-icon>
            </ion-fab-button>
          </ion-fab-list>

          <ion-fab-list side="end">
            <ion-fab-button>
              <ion-icon :icon="chevronForward"></ion-icon>
            </ion-fab-button>

            <ion-fab-button>
              <ion-icon :icon="chevronForward"></ion-icon>
            </ion-fab-button>
          </ion-fab-list>

          <ion-fab-list side="bottom">
            <ion-fab-button>
              <ion-icon :icon="chevronDown"></ion-icon>
            </ion-fab-button>
          </ion-fab-list>

          <ion-fab-list side="start">
            <ion-fab-button>
              <ion-icon :icon="chevronBack"></ion-icon>
            </ion-fab-button>

            <ion-fab-button>
              <ion-icon :icon="chevronBack"></ion-icon>
            </ion-fab-button>
          </ion-fab-list>
        </ion-fab>

        <!-- Accordion -->
        <ion-accordion-group ref="accordionGroup">
          <ion-accordion value="first">
            <ion-item
              slot="header"
              color="light"
            >
              <ion-label>First Accordion</ion-label>
            </ion-item>

            <div
              class="ion-padding"
              slot="content"
            >
              First Content
            </div>
          </ion-accordion>

          <ion-accordion value="second">
            <ion-item
              slot="header"
              color="light"
            >
              <ion-label>Second Accordion</ion-label>
            </ion-item>

            <div
              class="ion-padding"
              slot="content"
            >
              Second Content
            </div>
          </ion-accordion>

          <ion-accordion value="third">
            <ion-item
              slot="header"
              color="light"
            >
              <ion-label>Third Accordion</ion-label>
            </ion-item>

            <div
              class="ion-padding"
              slot="content"
            >
              Third Content
            </div>
          </ion-accordion>
        </ion-accordion-group>

        <!-- Accordion Button -->
        <ion-button @click="toggleAccordion()">
          Toggle Second Accordion
        </ion-button>

        <!-- Checkbox Form -->
        <form @submit.prevent="submit">
          <ion-checkbox
            v-model="agree"
            helper-text="Agree to the terms before continuing"
            error-text="You must agree to the terms to continue"
            @ionChange="validateCheckbox"
            :class="{
              'ion-valid': isValid,
              'ion-invalid': isValid === false,
              'ion-touched': isTouched
            }"
          >
            I agree to the terms and conditions
          </ion-checkbox>

          <br />

          <ion-button
            type="submit"
            size="small"
          >
            Submit
          </ion-button>
        </form>

        <!-- Popovers -->
        <ion-button id="click-trigger">
          Left-Click Me
        </ion-button>

        <ion-popover
          trigger="click-trigger"
          trigger-action="click"
        >
          <ion-content class="ion-padding">
            Hello World!
          </ion-content>
        </ion-popover>

        <ion-button id="context-menu-trigger">
          Right-Click Me
        </ion-button>

        <ion-popover
          trigger="context-menu-trigger"
          trigger-action="context-menu"
        >
          <ion-content class="ion-padding">
            Hello World!
          </ion-content>
        </ion-popover>

        <ion-button id="hover-trigger">
          Hover Over Me
        </ion-button>

        <ion-popover
          trigger="hover-trigger"
          trigger-action="hover"
        >
          <ion-content class="ion-padding">
            Hello World!
          </ion-content>
        </ion-popover>

        <!-- Footer -->
        <ion-footer>
          <ion-toolbar>
            <ion-title>Footer</ion-title>
          </ion-toolbar>
        </ion-footer>

        <!-- Alert -->
        <ion-alert
          trigger="present-alert"
          header="A Short Title Is Best"
          sub-header="A Sub Header Is Optional"
          message="A message should be a short, complete sentence."
          :buttons="alertButtons"
        ></ion-alert>

        <!-- Pokémon List -->
        <ion-list>
          <ion-item>
            <ion-label>Pokémon Yellow</ion-label>
          </ion-item>

          <ion-item>
            <ion-label>Mega Man X</ion-label>
          </ion-item>

          <ion-item>
            <ion-label>The Legend of Zelda</ion-label>
          </ion-item>

          <ion-item>
            <ion-label>Pac-Man</ion-label>
          </ion-item>

          <ion-item>
            <ion-label>Super Mario World</ion-label>
          </ion-item>
        </ion-list>

        <!-- Image -->
        <ion-img
          alt="The Wisconsin State Capitol building in Madison, WI at night"
        ></ion-img>
      </div>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import {
  IonHeader,
  IonPage,
  IonTitle,
  IonToolbar,
  IonButton,
  IonAlert,
  IonItem,
  IonList,
  IonBreadcrumb,
  IonBreadcrumbs,
  IonContent,
  IonProgressBar,
  IonCard,
  IonCardHeader,
  IonCardSubtitle,
  IonCardTitle,
  IonChip,
  IonAvatar,
  IonLabel,
  IonIcon,
  IonFab,
  IonFabButton,
  IonFabList,
  IonAccordion,
  IonAccordionGroup,
  IonCheckbox,
  CheckboxCustomEvent,
  IonPopover,
} from '@ionic/vue';

import {
  close,
  closeCircle,
  pin,
  add,
  chevronBack,
  chevronDown,
  chevronForward,
  chevronUp,
} from 'ionicons/icons';

import { ref } from 'vue';

/* Accordion */
const accordionGroup = ref(null);

const toggleAccordion = () => {
  if (!accordionGroup.value) {
    return;
  }

  const nativeEl = accordionGroup.value.$el;

  if (nativeEl.value === 'second') {
    nativeEl.value = undefined;
  } else {
    nativeEl.value = 'second';
  }
};

/* Checkbox */
const agree = ref(false);
const isTouched = ref(false);
const isValid = ref<boolean | undefined>();

const validateCheckbox = (
  event: CheckboxCustomEvent<{ checked: boolean }>
) => {
  isTouched.value = true;
  isValid.value = event.detail.checked;
};

const submit = () => {
  validateCheckbox({
    detail: {
      checked: agree.value,
    },
  } as CheckboxCustomEvent<{ checked: boolean }>);
};
</script>

<style scoped>
#container {
  text-align: center;
  position: absolute;
  left: 0;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
}

#container strong {
  font-size: 20px;
  line-height: 26px;
}

#container p {
  font-size: 16px;
  line-height: 22px;
  color: #8c8c8c;
  margin: 0;
}

#container a {
  text-decoration: none;
}
</style>