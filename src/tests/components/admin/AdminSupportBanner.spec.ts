// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { afterEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { defineComponent, nextTick } from "vue";
import AdminSupportBanner from "../../../components/AdminSupportBanner.vue";

vi.mock("@nextcloud/l10n", () => ({
  t: (_app: string, text: string, parameters?: Record<string, string>) => {
    if (parameters === undefined) {
      return `tr:${text}`;
    }

    return Object.entries(parameters).reduce(
      (translated, [key, value]) => translated.replace(`{${key}}`, value),
      `tr:${text}`,
    );
  },
}));

vi.mock("@nextcloud/vue", () => ({
  NcButton: defineComponent({
    name: "NcButton",
    emits: ["click"],
    template:
      '<button type="button" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
  }),
  NcNoteCard: defineComponent({
    name: "NcNoteCard",
    template: "<div><slot /></div>",
  }),
}));

afterEach(() => {
  window.localStorage.clear();
  vi.restoreAllMocks();
});

describe("AdminSupportBanner", () => {
  it("renders translated banner copy", () => {
    const wrapper = mount(AdminSupportBanner);

    expect(wrapper.text()).toContain(
      "tr:Help keep Profile Fields sustainable.",
    );
    expect(wrapper.text()).toContain(
      "tr:Profile Fields is open source under the AGPL license and maintained by the LibreCode team, creators of LibreSign.",
    );
    expect(wrapper.text()).toContain(
      "tr:If your organization depends on it, please help us sustain its development and maintenance.",
    );
    expect(wrapper.text()).toContain("tr:Sponsor LibreSign");
    expect(wrapper.text()).toContain("tr:Maybe later");
    expect(wrapper.text()).toContain("tr:Give Profile Fields a ⭐ on GitHub");
    expect(wrapper.text()).toContain(
      "tr:Contact us for support or custom development",
    );
  });

  it("opens sponsor page when sponsor button is clicked", async () => {
    const openSpy = vi.spyOn(window, "open").mockImplementation(() => null);
    const wrapper = mount(AdminSupportBanner);

    await wrapper.get("button").trigger("click");

    expect(openSpy).toHaveBeenCalledWith(
      "https://github.com/sponsors/LibreCodeCoop",
      "_blank",
      "noopener,noreferrer",
    );
  });

  it("hides itself after dismiss and persists state", async () => {
    const wrapper = mount(AdminSupportBanner);

    const buttons = wrapper.findAll("button");
    await buttons[1].trigger("click");

    expect(
      wrapper
        .find('[data-testid="profile-fields-admin-support-banner"]')
        .exists(),
    ).toBe(false);
    expect(
      window.localStorage.getItem("profile_fields_support_banner_dismissed"),
    ).toBe("1");
  });

  it("starts hidden when dismissal key is already persisted", () => {
    window.localStorage.setItem("profile_fields_support_banner_dismissed", "1");

    const wrapper = mount(AdminSupportBanner);
    return nextTick().then(() => {
      expect(
        wrapper
          .find('[data-testid="profile-fields-admin-support-banner"]')
          .exists(),
      ).toBe(false);
    });
  });
});
